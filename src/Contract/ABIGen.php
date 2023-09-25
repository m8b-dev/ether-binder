<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\Contract;

use Exception as GlobalException;
use kornrunner\Keccak;
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
use PhpParser\Builder\Param;
use PhpParser\BuilderFactory;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Cast\Int_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinter\Standard;

/**
 * Core class for generating ABI bindings.
 *
 * @author DubbaThony
 */
class ABIGen
{
	protected array $abiFunctions;
	protected array $abiEvents;
	protected array $abiConstructor;
	protected string $className;
	protected array $tuplesRegistry = [];
	protected const autogenWarning = <<<HDC
// Code auto generated - DO NOT EDIT.
// This file is a generated binding and any manual changes will be lost.
// If you need to edit this class, extend it.
HDC;

	protected const throwsCommentDeploy = <<<HDC
/**
	 * @throws \M8B\EtherBinder\Exceptions\EthBinderArgumentException
	 * @throws \M8B\EtherBinder\Exceptions\EthBinderLogicException
	 * @throws \M8B\EtherBinder\Exceptions\EthBinderRuntimeException
	 * @throws \M8B\EtherBinder\Exceptions\InvalidLengthException
	 * @throws \M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException
	 * @throws \M8B\EtherBinder\Exceptions\UnexpectedUnsignedException
	 * @throws \M8B\EtherBinder\Exceptions\RPCGeneralException
	 * @throws \M8B\EtherBinder\Exceptions\RPCNotFoundException
	 */
HDC;

	protected const throwsCommentCall = <<<HDC
/**
	 * @throws \M8B\EtherBinder\Exceptions\EthBinderLogicException
	 * @throws \M8B\EtherBinder\Exceptions\EthBinderRuntimeException
	 * @throws \M8B\EtherBinder\Exceptions\EthBinderArgumentException
	 * @throws \M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException
	 */
HDC;

	protected const throwsCommentTransact = <<<HDC
/**
	 * @throws \M8B\EtherBinder\Exceptions\UnexpectedUnsignedException
	 * @throws \M8B\EtherBinder\Exceptions\EthBinderLogicException
	 * @throws \M8B\EtherBinder\Exceptions\InvalidLengthException
	 * @throws \M8B\EtherBinder\Exceptions\EthBinderRuntimeException
	 * @throws \M8B\EtherBinder\Exceptions\EthBinderArgumentException
	 * @throws \M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException
	 */
HDC;
	protected const throwsCommentFilterFetch = <<<HDC
/**
	* @throws \M8B\EtherBinder\Exceptions\EthBinderLogicException
	* @throws \M8B\EtherBinder\Exceptions\RPCGeneralException
	* @throws \M8B\EtherBinder\Exceptions\EthBinderRuntimeException
	* @throws \M8B\EtherBinder\Exceptions\BadAddressChecksumException
	* @throws \M8B\EtherBinder\Exceptions\InvalidHexLengthException
	* @throws \M8B\EtherBinder\Exceptions\RPCNotFoundException
	* @throws \M8B\EtherBinder\Exceptions\InvalidHexException
	* @throws \M8B\EtherBinder\Exceptions\EthBinderArgumentException
	* @throws \M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException
	*/
HDC;


	/**
	 * @throws EthBinderArgumentException
	 */
	protected function __construct(array $abi, protected ?string $compiledBlob = null)
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

	/**
	 * This function returns array of generated bindings code. Bindings include tuples, events and contract.
	 *
	 * Output array shape is:
	 * [
	 *     "contract" => "<?php root source code",
	 *     "events"   => [
	 *         "PHPClassName" => "<?php event source code",
	 *         "PHPClassName" => "<?php event source code"
	 *     ]
	 *     "tuples"   => [
	 *         "PHPClassName" => "<?php tuple source code"
	 *     ]
	 * ]
	 *
	 * ABIGen accepts 2 files, one is ABI JSON file, which is required, and should be sourced from solidity
	 *
	 * LIMITATIONS:
	 *  currently event allows parsing event data into appropriate object (to "events" sub-array class). There is a rare
	 *  case of having events that emit indexed dynamic data such as strings, arrays or tuples. Solidity in such case
	 *  returns keccak256 hash of such data, not the data itself, making the data itself unrecoverable. If the ABIGen
	 *  stumbles upon such event, it will throw NotSupportedException. Such events are not supported. In pinch, it's
	 *  OK to remove the event from ABI JSON manually, but of course, such events will not be parsed.
	 *
	 * @throws NotSupportedException
	 * @throws EthBinderArgumentException
	 */
	public static function generate(string $fullyQualifiedClassName, array $abi, ?string $compiledBlob = null): array
	{
		$generator = new static($abi, $compiledBlob);
		return $generator->gen($fullyQualifiedClassName);
	}

	/**
	 * @throws NotSupportedException
	 * @throws EthBinderArgumentException
	 */
	protected function gen(string $fullyQualifiedClassName): array
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
			$exploded  = explode("\\", $fullyQualifiedClassName);
			$className = array_pop($exploded);
			$namespace = implode("\\", $exploded);
		}

		$this->className = $className;

		$bld = new BuilderFactory();

		// Embed important data into code in case original abi files are lost and need reproducing, or library user needs
		// them. In general, they shouldn't be needed in the library itself or bindings.
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
				"type"            => $fType)
		) {
			$fname = $abiArr[$k]["name"] ?? ""; // not all elements of array have this key
			$outs  = $abiArr[$k]["outputs"] ?? []; // not all elements of array have this key
			// constructor doesn't have function name, so use deployNew with class name supplied by caller (usually CLI)
			// slapped onto it with UCFirst just in case someone doesn't follow PascalCase for class names.
			if($fType === "constructor") {
				$outs = [];
				$fname = "deployNew".ucfirst($this->className);
			}
			if(in_array($fname, $processedNames)) {
				// It is possible that solidity generates 2 functions of same name. At the moment there is no logic
				// to add for example _0 or _1 to the function names, and if it was added, the binding usage would become
				// a bit more confusing. For now warning user during generation should suffice.
				trigger_error("function $fname appears in abi file more than once. Compiler can produce such output, "
				."and it's fine, but subsequent definitions of same functions are not supported. Skipping this occurrence.", E_USER_WARNING);
				continue;
			}
			$processedNames[] = $fname;

			// abstractCall refers to function call on abstract class that binding will extend
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
			// return signature is needed for ABI decoding response from contract. Right now ABI decoder uses function
			// signatures such as "foo(uint256,(uint256,uint256[])[3])" while building types tree. Name of "function" is
			// ignored and can whatever non-empty [a-zA-Z\d] string
			$retSignature = $this->buildMethodParams($fname, $outs, $bld)["signature"];

			if($abstractCall === "mkCall") {
				$throwsComment = static::throwsCommentCall;
				$retType = $this->getPhpTypingFromOutputs($outs);
				// getPhpTypingFromOutputs will be set only on this branch, second branch means the Transaction will be
				// returned by binding function. That's because in case of bug that the retPostProcessMeta is used when
				// not needed, the php generated warning will be useful notification something went wrong
				$retPostProcessMeta = $this->prepareOutputTupleInfo($outs, $namespace);
			} else {
				$throwsComment = static::throwsCommentTransact;
				$retType = "\\".Transaction::class;
			}

			// pramRefs is references to parameters (what will be put into abstract class call as params).
			// paramsBuilt is parameters to the binding's function
			// CONTRACT constructor (NOT PHP constructor) is called statically and needs RPC and PrivateKey for the
			// transaction. These are always first 2 params.
			if($fType === "constructor") {
				$throwsComment = static::throwsCommentDeploy;
				array_unshift($paramsBuilt, $bld
					->param("privateKey")
					->setType("\\" . Key::class)
					->addAttribute(new Attribute(new Name("\\SensitiveParameter")))); // mask key param in case of crash
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

			// if function is payable, first param should be eth value of transaction
			if($smut == "payable") {
				array_unshift($paramsBuilt, $bld->param("transactionValue")->setType("\\".OOGmp::class));
				$paramRefs[] = $bld->var("transactionValue");
				$idx++;
			}
			foreach($paramNames AS $paramName)
				$paramRefs[$idx][] = $bld->var($paramName);

			if($fType === "constructor") {
				// constructor can be payable. There is another abstract classes' handler for this, therefore requires
				// different function call
				$functionInternal = new Return_($bld->staticCall("static", $smut == "payable" ?
					"runPayableDeploy" : "runNonPayableDeploy", $paramRefs));
			} else {
				if($retType != "\\".Transaction::class) {
					$functionInternal = new Return_(
						$bld->methodCall($bld->var("this"), "parseOutput", [
							$bld->methodCall($bld->var("this"), $abstractCall, $paramRefs),
							$bld->val($retSignature),
							$bld->val($retPostProcessMeta)
						])
					);
				} else {
					$functionInternal = new Return_(
						$bld->methodCall($bld->var("this"), $abstractCall, $paramRefs)
					);
				}
			}

			// plug in bound contract method to contract
			$method = $bld->method($fname)
				->makePublic()
				->addParams($paramsBuilt)
				->addStmts($validators)
				->setReturnType($retType)
				->addStmt($functionInternal)
				->setDocComment($throwsComment);
			if($fType === "constructor")
				$method = $method->makeStatic();
			$class->addStmt($method);
		}

		$eventsGen = $this->generateEventClasses($namespace);
		// We have generated root class and event classes, so we collected in helper functions info about all tuples
		// that could possibly come up. It is possible contract has more tuples in its implementation, but none of them
		// got surfaced in ABI, so we can safely ignore them as they will never come up
		$tuplesGen = $this->generateTuples($namespace);

		// Later down the line to be able to parse events we will need to enumerate them, to check against known events
		// for the contract, so a registry needs to be constructed
		$eventsRegistry = [];
		foreach(array_keys($eventsGen["events"]) AS $eventName) {
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
		// add note

		if($hasNamespace) {
			$nodes = [
				$bld->namespace(ltrim($namespace, "\\"))
					->setDocComment(static::autogenWarning)
					->addStmt($class)
					->getNode()
			];
		} else {
			$class->setDocComment(static::autogenWarning);
			$nodes = [$class->getNode()];
		}
		return [
			"contract" => (new Standard())->prettyPrintFile($nodes),
			"events"   => array_merge($eventsGen["events"], $eventsGen["filters"]),
			"tuples"   => $tuplesGen
		];
	}

	/**
	 * @throws NotSupportedException
	 * @throws GlobalException
	 */
	protected function generateEventClasses(string $namespace): array
	{
		$o = [];
		foreach($this->abiEvents AS $event) {
			$className       = $this->className . "Event" . ucfirst($event["name"]);
			$filterClassName = $this->className . "Filter" . ucfirst($event["name"]);
			$bld             = new BuilderFactory();
			$eventAsFnPrms   = $this->buildMethodParams($event["name"], $event["inputs"], $bld);
			$rawSignatureID  = Keccak::hash($eventAsFnPrms["signature"], 256, true);

			$filterClass = $bld->class($filterClassName)
				->extend("\\".AbstractEventFilter::class)
				->addStmt($bld->method("eventClassName")
					->makePublic()
					->makeStatic()
					->setReturnType("string")
					->addStmt(new Return_($bld->val($namespace."\\".$className))))
				->addStmt($bld->method("fetchNext")
					->makePublic()
					->setReturnType("?".$namespace."\\".$className)
					->addStmt(new Return_(new MethodCall(
						new Variable("this"),
						new Identifier("parseFetchNext")
					)))
					->setDocComment(static::throwsCommentFilterFetch));

			$filterClassConstructor = $bld->method("__construct")
				->makePublic()
				->addParam(
					$bld->param("rpc")
						->setType("\\".AbstractRPC::class))
				->addParam(
					$bld->param("target")
						->setType("\\".Address::class))
				->addStmt(new Assign(
						new PropertyFetch(
							new Variable("this"),
							new Identifier("rpc")),
					new Variable("rpc")
				))
				->addStmt(new Assign(
						new PropertyFetch(
							new Variable("this"),
							new Identifier("target")),
					new Variable("target")
				));
			$filterIndexedPhpTypes = [];

			$class = $bld->class($className)
				->extend("\\".AbstractEvent::class)
				->addStmt($bld->method("abiEventData")
					->makePublic()
					->makeStatic()
					->setReturnType("array")
					->addStmt(
						new Return_($bld->val($event))
					))
				->addStmt($bld->method("abiEventID")
					->makePublic()
					->makeStatic()
					->setReturnType("string")
					->addStmt(
						new Return_($bld->funcCall("hex2bin", [$bld->val(bin2hex($rawSignatureID))]))
					));
			$offsetIndexed    = 0;
			$offsetNonIndexed = 0;
			// these 2 variables are used to create 2 signatures of event for decoder, decoder works universally on
			//  function-like signatures for typing. This allows shifting complexity from logic in parser (supports only one
			//  approach) to abigen. And signatures are nicest as they allow dynamic usage without generating bindings
			//  which may come in handy time to time.
			$evInputsData    = [];
			$evInputsIndexed = [];

			foreach($event["inputs"] AS $eventInputs) {
				list("indexed"=>$indexed, "internalType"=>$internalType, "name"=>$name, "type"=>$type) = $eventInputs;
				// check if type will return keccak256 hash of data or data while parsing event.
				if(
					   $indexed
					&& (   // bytesNUM are OK, since they are static
					       !(str_starts_with("bytes", $type) && rtrim($type, "1234567890" != $type))
						&& !in_array(rtrim($type, "1234567890"), ["int", "uint", "address", "bool"]))
				) {
					throw new NotSupportedException("Event ".$event["name"]." contains indexed type $type, which"
						." is not supported. This is because decoding indexed complex type is not really a thing. See"
						." solidity abi-spec documentation, section \"events\". This library assumes that all event data"
						." is always decodable, and this would break PHP typings. In short, the indexed field will "
						."contain Keccak hash of data. Consider changing solidity code to contain dynamic data in".
						" non-indexed values of event.");
				}

				$reference = new ArrayDimFetch(new Variable("this"),
					new String_(($indexed?"topic-".$offsetIndexed:"data-".$offsetNonIndexed)));

				$phpEventFieldType = $this->getPhpTypingFromType($type, $internalType);

				if($indexed) {
					$evInputsIndexed[] = $eventInputs;

					$filterClassConstructor
						->addParam($bld->param("ev".ucfirst($name))->setType($phpEventFieldType."|array|null"))
						->addStmt(new Assign(
							new ArrayDimFetch(
								new PropertyFetch(
									new Variable("this"),
									new Identifier("filterParams")),
								$bld->val($offsetIndexed)),
							new Variable("ev".ucfirst($name))
						));
					$filterIndexedPhpTypes[] = new ArrayItem(new String_(ltrim($phpEventFieldType, "?")));
					$offsetIndexed++;
				} else {
					$evInputsData[] = $eventInputs;
					$offsetNonIndexed++;
				}

				// getPhpTypingFromType registers if it's tuple, so we can use tuple registry for return type for this
				// specific event for ABIEncoder.
				$class->addStmt($bld->method("get".ucfirst($name))
					->makePublic()
					->setReturnType($phpEventFieldType)
					->addStmt(
						new Return_($reference)
					)
				);
				$class->addStmt($bld->method("set".ucfirst($name))
					->makePublic()
					->addParam(
						$bld->param("value")
							->setType($phpEventFieldType))
					->addStmt(
						new Assign($reference, $bld->var("value"))
					)
				);
			}

			$filterClass->addStmt(
				$bld
					->method("validatorTypes")
					->makeProtected()
					->makeStatic()
					->setReturnType("array")
					->addStmt(new Return_(new Array_($filterIndexedPhpTypes))));

			$indexedSig = empty($evInputsData)
				? null : $this->buildMethodParams("boundEvent", $evInputsData, $bld)["signature"];
			$class->addStmt($bld->method("abiDataSignature")
				->makePublic()
				->makeStatic()
				->setReturnType("?string")
				->addStmt(new Return_($bld->val($indexedSig)))
			);
			// it may be counter-intuitive that event inputs is used for method designed for method outputs. These structs
			// have enough in common - same notation is used for tuples, and this method is concerned about tuples only.
			$retPostProcessMeta = $indexedSig === null ? null : $this->prepareOutputTupleInfo($event["inputs"], $namespace);
			$class->addStmt($bld->method("abiDataTupleReplacements")
				->makePublic()
				->makeStatic()
				->setReturnType("?array")
				->addStmt(new Return_($bld->val($retPostProcessMeta)))
			);

			$class->addStmt($bld->method("abiIndexedSignature")
				->makePublic()
				->makeStatic()
				->setReturnType("string")
				->addStmt(new Return_($bld->val(
					empty($evInputsIndexed) ? null : $this->buildMethodParams("boundEvent", $evInputsIndexed, $bld)["signature"]
				)))
			);

			$filterClass->addStmt($filterClassConstructor);

			if(!empty(ltrim($namespace, "\\"))) {
				$nodes = [
					$bld->namespace(ltrim($namespace, "\\"))
						->setDocComment(static::autogenWarning)
						->addStmt($class)
						->getNode()
				];
				$nodesFilter = [
					$bld->namespace(ltrim($namespace, "\\"))
						->setDocComment(static::autogenWarning)
						->addStmt($filterClass)
						->getNode()
				];
			} else {
				$class->setDocComment(static::autogenWarning);
				$nodes = [$class->getNode()];
				$filterClass->setDocComment(static::autogenWarning);
				$nodesFilter = [$filterClass->getNode()];
			}
			$o["events"][$className] = (new Standard())->prettyPrintFile($nodes);
			$o["filters"][$filterClassName] = (new Standard())->prettyPrintFile($nodesFilter);
		}
		return $o;
	}

	/**
	 * @throws NotSupportedException
	 */
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
					$bld->namespace(ltrim($namespace, "\\"))
						->setDocComment(static::autogenWarning)
						->addStmt($class)
						->getNode()
				];
			} else {
				$class->setDocComment(static::autogenWarning);
				$nodes = [$class->getNode()];
			}
			$o[$className] = (new Standard())->prettyPrintFile($nodes);
		}
		return $o;
	}

	/**
	 * @return array{params: Param[], names: string[], validators: Assign[], signature: string}
	 * @throws NotSupportedException
	 */
	protected function buildMethodParams(string $fnName, array $inputs, BuilderFactory $bld): array
	{
		// see https://docs.soliditylang.org/en/latest/abi-spec.html
		$signature  = $fnName."(";
		$validators = [];
		$names      = [];
		$params     = [];
		$firstIt    = true;

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
				$arr       = false;
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
						$bld->val($size)])
				);
				$param->setType("string");
			} elseif($type == "tuple") {
				$this->registerTuple($inputs[$key]);
				$param->setType($this->tupleInternalTypeToType($internalType));
			} else {
				throw new NotSupportedException("type $type was not recognized abi type");
			}
			$params[] = $param;
			$names[]  = $name;
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

	/**
	 * @throws NotSupportedException
	 */
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

	protected function prepareOutputTupleInfo(array $abiOutputs, string $namespace): null|array
	{
		// transform each ABI entry into array that contains 2 keys:
		//  ["children"=> null|[], "tuple":"FQCN"]
		// where children should be null if there are no more children with non-null tuple to cut off real runtime
		// (binding generation can be slower but binding itself shouldn't do unessesery steps).
		// Output will be walked during parsing call output by AbstractContract::parseOutput
		// to create correct types bindings instead of just having general-purpose array.
		$result = [];

		foreach ($abiOutputs as $output) {
			$result[] = $this->innerPrepareOutputTupleInfo($output, $namespace);
		}

		if ($this->emptyr($result)) {
			return null;
		}

		return $result;
	}

	protected function innerPrepareOutputTupleInfo(array $abiOutputs, string $namespace): null|array
	{
		$result = [
			"tuple"    => null,
			"children" => []
		];

		if(!isset($abiOutputs["components"]))
			return null;

		$result["tuple"] = rtrim($namespace, "\\")."\\".$this->tupleInternalTypeToType($abiOutputs["internalType"]);
		foreach($abiOutputs["components"] AS $output) {
			$res                  = $this->innerPrepareOutputTupleInfo($output, $namespace);
			$result["children"][] = $this->emptyr($res) ? null : $res;
		}
		return $result;
	}

	private function emptyr($var): bool
	{
		if(empty($var))
			return true;
		if(!is_array($var))
			return false;
		foreach($var AS $itm)
			if(!$this->emptyr($itm))
				return false;
		return true;
	}

	/**
	 * @throws NotSupportedException
	 */
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
		foreach($components AS $component)
			if(!empty($component["components"]))
				$this->registerTuple($component);
	}
}
