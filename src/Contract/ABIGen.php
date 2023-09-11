<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract;

use M8B\EtherBinder\Common\Address;
use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Common\SolidityFunction;
use M8B\EtherBinder\Common\SolidityFunction4BytesSignature;
use M8B\EtherBinder\Common\Transaction;
use M8B\EtherBinder\Crypto\Key;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\NotSupportedException;
use M8B\EtherBinder\RPC\AbstractRPC;
use M8B\EtherBinder\Utils\OOGmp;
use PhpParser\BuilderFactory;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinter\Standard;

class ABIGen
{
	protected array $abiFunctions;
	protected array $abiEvents;
	protected array $abiConstructor;
	protected string $className;
	protected array $tuplesRegistry = [];

	public function __construct(array $abi, protected ?string $compiledBlob = null)
	{
		$this->abiFunctions   = [];
		$this->abiEvents      = [];
		$this->abiConstructor = [];

		foreach($abi AS $abiItem)
		{
			$this->{match ($abiItem["type"]){
				"event"       => "abiEvents",
				"constructor" => "abiConstructor",
				"function"    => "abiFunctions",
				default       => throw new EthBinderArgumentException("abi element type '" . $abiItem["type"] . "' not recognized")
			}}[] = $abiItem;
		}
	}

	public function gen(string $fullyQualifiedClassName): array
	{
		$this->tuplesRegistry = [];
		if(empty($fullyQualifiedClassName))
			throw new EthBinderArgumentException();
		// split fqcn to namespace and class
		$hasNamespace = str_contains(substr($fullyQualifiedClassName, 1), "\\");
		if(!$hasNamespace) {
			$className = ltrim($fullyQualifiedClassName, "\\"); // valid FQCN is "FooClass" and "\FooClass"
			$namespace = "\\";
			trigger_error("generating into global namespace, this is most likely unwanted", E_USER_WARNING);
		} else {
			if($fullyQualifiedClassName[0] !== "\\")
				// prohibit namespace\class as someone might mistake it for \current\namespace\class etc.
				throw new EthBinderArgumentException("first character of FQCN must be '\\', at least in this implementation");
			$exploded = explode("\\", $fullyQualifiedClassName);
			$className = array_pop($exploded);
			$namespace = implode("\\", $exploded);
		}

		$this->className = $className;

		$bld = new BuilderFactory();

		$class = $bld->class($this->className)
			->extend("\\".AbstractContract::class)
			->addStmt($bld->method("abi")
				->makePublic()
				->makeStatic()
				->setReturnType("string")
				->addStmt(
					new Return_($bld->val(json_encode(array_merge($this->abiFunctions, $this->abiConstructor, $this->abiEvents))))
				)
			)
			->addStmt($bld->method("bytecode")
				->makePublic()
				->makeStatic()
				->setReturnType("?string")
				->addStmt(
					new Return_($bld->val($this->compiledBlob))
				)
			);
		$processedNames = [];

		$abiArr = $this->abiFunctions;
		if($this->compiledBlob !== null)
			$abiArr = array_merge($this->abiConstructor, $this->abiFunctions);
		foreach($abiArr AS $k => list(
				"stateMutability" => $smut,
				"inputs"          => $prms,
				"outputs"         => $outs,
				"type"            => $fType)
		) {
			$fname = $abiArr[$k]["name"] ?? ""; // not all elements of array have this key
			$outs  = $abiArr[$k]["outputs"] ?? ""; // not all elements of array have this key
			if($fType === "constructor") {
				$outs = [];
				$fname = "deployNew".ucfirst($this->className);
			}
			if(in_array($fname, $processedNames)) {
				trigger_error("function $fname appears in abi file more than once. Compiler can produce such output, "
				."and it's fine, but subsequent definitions of same functions are not supported. Skipping this occurrence.", E_USER_WARNING);
				continue;
			}
			$processedNames[] = $fname;

			$abstractCall = match($smut) {
				"pure", "view" => "mkCall",
				"nonpayable"   => "mkTxn",
				"payable"      => "mkPayableTxn",
				default => throw new NotSupportedException("state mutability '$smut' is not supported")
			};
			list("params"    => $paramsBuilt,
				"names"      => $paramNames,
				"validators" => $validators,
				"signature"  => $fnSignature) = $this->buildMethodParams($fname, $prms, $bld);
			$retSignature = $this->buildMethodParams($fname, $outs, $bld)["signature"];

			if($abstractCall === "mkCall") {
				$retType = $this->getPhpTypingFromOutputs($outs);
			} else {
				$retType = "\\".Transaction::class;
			}

			if($fType === "constructor") {
				array_unshift($paramsBuilt, $bld
					->param("privateKey")
					->setType("\\" . Key::class)
					->addAttribute(new Attribute(new Name("\\SensitiveParameter"))));
				array_unshift($paramsBuilt, $bld->param("rpcToDeployWith")->setType("\\" . AbstractRPC::class));
				$paramRefs = [
					$bld->val($fnSignature),
					$bld->var("privateKey"),
					$bld->var("rpcToDeployWith")
				];
				$idx = 3;
			} else {
				$paramRefs = [$fnSignature];
				$idx = 1;
			}
			if($smut == "payable") {
				array_unshift($paramsBuilt, $bld->param("transactionValue")->setType("\\".OOGmp::class));
				$paramRefs[] = $bld->var("transactionValue");
				$idx++;
			}
			foreach($paramNames AS $paramName)
				$paramRefs[$idx][] = $bld->var($paramName);

			if($fType === "constructor") {
				$functionInternal = new Return_($bld->staticCall("static", $smut == "payable" ?
					"runPayableDeploy" : "runNonPayableDeploy", $paramRefs));
			} else {
				if($retType != "\\".Transaction::class) {
					$functionInternal = new Return_(
						$bld->methodCall($bld->var("this"), "parseOutput", [
							$bld->methodCall($bld->var("this"), $abstractCall, $paramRefs),
							$bld->val($retSignature)
						])
					);
				} else {
					$functionInternal = new Return_(
						$bld->methodCall($bld->var("this"), $abstractCall, $paramRefs)
					);
				}
			}

			$method = $bld->method($fname)
				->makePublic()
				->addParams($paramsBuilt)
				->addStmts($validators)
				->setReturnType($retType)
				->addStmt($functionInternal);
			if($fType === "constructor")
				$method = $method->makeStatic();
			$class->addStmt($method);
		}

		$eventsGen = $this->generateEventClasses($namespace);
		$tuplesGen = $this->generateTuples($namespace);

		$eventsRegistry = [];
		foreach(array_keys($eventsGen) AS $eventName) {
			$eventsRegistry[] = $bld->classConstFetch($eventName,"class");
		}

		$class->addStmt($bld->method("getEventsRegistry")
				->makeProtected()
				->makeStatic()
				->setReturnType("array")
				->addStmt(new Return_($bld->val(
					$eventsRegistry
				)))
		);
		$class->setDocComment("/// Autogenerated source code");
		if($hasNamespace) {
			$nodes = [
				$bld->namespace(ltrim($namespace, "\\"))->addStmt($class)->getNode()];
		} else {
			$nodes = [$class->getNode()];
		}
		return [
			"contract" => (new Standard())->prettyPrintFile($nodes),
			"events"   => $eventsGen,
			"tuples"   => $tuplesGen
		];
	}

	protected function generateEventClasses(string $namespace): array
	{
		$o = [];
		foreach($this->abiEvents AS $event) {
			$className = $this->className . "Event" . ucfirst($event["name"]);
			$bld = new BuilderFactory();


			$class = $bld->class($className)
				->extend("\\".AbstractEvent::class)
				->addStmt($bld->method("getEventData")
					->makePublic()
					->makeStatic()
					->setReturnType("array")
					->addStmt(
						new Return_($bld->val($event))
					));
			foreach($event["inputs"] AS list("indexed"=>$indexed, "internalType"=>$internalType, "name"=>$name, "type"=>$type)) {
				$class->addStmt($bld->method("get".ucfirst($name))
					->makePublic()
					->setReturnType($this->getPhpTypingFromType($type, $internalType))
					->addStmt(
						new Return_($bld->methodCall($bld->var("this"), "getDataByName", [$name]))
					)
				);
			}
			if(!empty(ltrim($namespace, "\\"))) {
				$nodes = [
					$bld->namespace(ltrim($namespace, "\\"))->addStmt($class)->getNode()
				];
			} else {
				$nodes = [$class->getNode()];
			}
			$o[$className] = (new Standard())->prettyPrintFile($nodes);
		}
		return $o;
	}

	protected function generateTuples(string $namespace): array
	{
		$o = [];

		foreach($this->tuplesRegistry AS $className => $tupleData) {
			$bld = new BuilderFactory();

			$class = $bld->class($className)
				->extend("\\".AbstractTuple::class)
				->addStmt($bld->method("getTupleData")
					->makePublic()
					->makeStatic()
					->setReturnType("array")
					->addStmt(
						new Return_($bld->val($tupleData))
					));
			foreach($tupleData AS $k => list("internalType" => $internalType, "name" => $name, "type" => $type)) {
				if(empty($name)) {
					$name = "unknownNameIndex_".$k;
				}
				$phpType = $this->getPhpTypingFromType($type, $internalType);

				$class->addStmt($bld->method("get".ucfirst($name))
					->makePublic()
					->setReturnType($phpType)
					->addStmt(
						new Return_($bld->methodCall($bld->var("this"), "offsetGet", [$k]))
					)
				);
				$class->addStmt($bld->method("set".ucfirst($name))
					->makePublic()
					->setReturnType("void")
					->addParam($bld->param("val")->setType($phpType))
					->addStmt(
						$bld->methodCall($bld->var("this"), "offsetSet", [$bld->val($k), $bld->var("val")])
					)
				);
			}
			if(!empty(ltrim($namespace, "\\"))) {
				$nodes = [
					$bld->namespace(ltrim($namespace, "\\"))->addStmt($class)->getNode()
				];
			} else {
				$nodes = [$class->getNode()];
			}
			$o[$className] = (new Standard())->prettyPrintFile($nodes);
		}
		return $o;
	}

	protected function buildMethodParams(string $fnName, array $inputs, BuilderFactory $bld)
	{
		// see https://docs.soliditylang.org/en/latest/abi-spec.html
		$signature = $fnName."(";
		$validators = [];
		$names = [];
		$params = [];
		$firstIt = true;

		$fallbackNameMissingCounter = 0;

		foreach($inputs AS $key => list("name" => $name, "type" => $type, "internalType" => $internalType)) {
			if(empty($name)) {
				$name = "unnamedSolidityArgument_".$fallbackNameMissingCounter;
				$fallbackNameMissingCounter++;
			}

			if($firstIt)
				$firstIt = false;
			else
				$signature .= ",";

			if(str_starts_with($type, "tuple"))
				$signature .= $this->buildTupleSignatureFromTuple($inputs[$key]);
			else
				$signature .= $type;

			$param = $bld->param($name);
			if($type === "uint" || $type == "int") {
				$param->setType("\\".OOGmp::class);
			} elseif(str_starts_with($type, "uint") || str_starts_with($type, "int")) {
				// remove characters, to get bits count
				$bitsCount = (int)ltrim($type, "uint");
				$arr = false;
				if(str_contains($type, "[")) {
					$param->setType("array");
					$arr = true;
				} elseif($bitsCount <= 32) {
					$param->setType("int");
				} else {
					$param->setType("\\".OOGmp::class);
				}
				$validators [] = new Assign(
					$bld->var($name),
					$bld->methodCall($bld->var("this"), $arr ? "expectIntArrOfSize" : "expectIntOfSize", [
						$bld->val($type[0] === "u"),
						$bld->var($name),
						$bld->val($bitsCount)])
				);
			} elseif($type == "address") {
				$param->setType("\\".Address::class);
			} elseif($type == "bool" || $type == "boolean") {
				$param->setType("bool");
			} elseif(str_starts_with($type, "fixed") || str_starts_with($type, "ufixed")) {
				// see https://docs.soliditylang.org/en/latest/abi-spec.html specific quite:
				// Fixed point numbers are not fully supported by Solidity yet. They can be declared, but cannot be assigned to or from.
				//
				// which basically means they are allocated, but cannot be written or read - rendering type useless.
				throw new NotSupportedException("fixed types are not supported in abigen nor in solidity");
			} elseif(str_contains($type, "[")) {
				$param->setType("array");
			} elseif($type == "function") {
				$param->setType("\\".SolidityFunction4BytesSignature::class);
			} elseif($type == "bytes32") {
				$param->setType("\\".Hash::class);
			} elseif($type == "string") {
				$param->setType("string");
			} elseif(str_starts_with($type, "bytes")) {
				$size = (int)ltrim($type, "bytes");
				$validators [] = new Assign(
					$bld->var($name),
					$bld->methodCall($bld->var("this"), "expectBinarySizeNormalizeString", [
						$bld->var($name),
						$bld->val((int)$size)])
				);
				$param->setType("string");
			} elseif($type == "tuple") {
				$this->registerTuple($inputs[$key]);
				$param->setType($this->tupleInternalTypeToType($internalType));
			} else {
				throw new NotSupportedException("type $type was not recognized abi type");
			}
			$params[] = $param;
			$names[] = $name;
		}
		return ["params" => $params, "names" => $names, "validators" => $validators, "signature" => $signature . ")"];
	}

	protected function buildTupleSignatureFromTuple(array $tupleAbiData): string
	{
		$type = $tupleAbiData["type"];
		if($type == "tuple")
			$suffix = "";
		else
			$suffix = substr($type, strlen("tuple"));
		$o = "(";

		$first = true;
		foreach($tupleAbiData["components"] AS $k => list("type" => $type)) {
			if($first)
				$first = false;
			else
				$o .= ",";
			if(str_starts_with($type, "tuple")) {
				$o .= $this->buildTupleSignatureFromTuple($tupleAbiData["components"][$k]);
				continue;
			}
			$o .= $type;
		}

		return $o.")$suffix";
	}

	protected function getPhpTypingFromType(string $type, string $internalType): string
	{
		if(str_contains($type, "[") && $type !== "byte[]")
			return "array";

		if(str_starts_with($type, "uint") || str_starts_with($type, "int"))
			return "\\".OOGmp::class;

		if(str_starts_with($type, "bytes") && $type !== "bytes32")
			return "string";

		return match($type) {
			"bytes32"          => "\\".Hash::class,
			"byte[]", "string" => "string",
			"function"         => "\\".SolidityFunction::class,
			"bool", "boolean"  => "bool",
			"address"          => "\\".Address::class,
			"tuple"            => $this->tupleInternalTypeToType($internalType),
			default            => throw new NotSupportedException("output type $type is not supported")
		};
	}

	protected function getPhpTypingFromOutputs(array $outputs): string
	{
		foreach($outputs AS $o) {
			if(ltrim($o["type"], "[1234567890]") == "tuple")
				$this->registerTuple($o);
		}

		if(empty($outputs))
			return "void";

		if(count($outputs) > 1)
			return "array";

		return $this->getPhpTypingFromType($outputs[0]["type"], $outputs[0]["internalType"]);
	}

	protected function tupleInternalTypeToType(string $internalName): string
	{
		$internalName = rtrim($internalName, "[1234567890]");
		return $this->className."Tuple".substr($internalName, strrpos($internalName, ".") +1);
	}

	protected function registerTuple(array $tupleData): void
	{
		list("internalType" => $internalType, "components" => $components)   = $tupleData;
		$this->tuplesRegistry[$this->tupleInternalTypeToType($internalType)] = $components;
	}
}