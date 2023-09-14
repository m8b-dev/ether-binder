<?php

namespace M8B\EtherBinder\Contract;


use M8B\EtherBinder\Common\Hash;
use M8B\EtherBinder\Common\Log;
use M8B\EtherBinder\Exceptions\EthBinderArgumentException;
use M8B\EtherBinder\Exceptions\EthBinderLogicException;
use M8B\EtherBinder\Exceptions\InvalidLengthException;

/**
 * AbstractEvent is an abstract class that represents an Ethereum contract event.
 * It's parent to all ABI binding events and provides parsing driver code.
 *
 * @author DubbaThony
 */
abstract class AbstractEvent extends AbstractArrayAccess
{
	abstract public static function abiEventData():              array ;
	abstract public static function abiEventID():                string;
	abstract public static function abiIndexedSignature():       string;
	abstract public static function abiDataSignature():         ?string;
	abstract public static function abiDataTupleReplacements(): ?array ;


	/**
	 * Parses an Ethereum contract event from a Log object grabbed from Receipt.
	 *
	 * @param Log $log The log data to parse.
	 * @throws EthBinderLogicException
	 * @throws InvalidLengthException
	 * @throws EthBinderArgumentException
	 * @return static|null Returns an instance of the concrete class that extends AbstractEvent or null.
	 */
	public static function parseEventFromLog(Log $log): ?static
	{
		if(static::class == self::class)
			throw new EthBinderLogicException("parseEventsFromLog was called on AbstractEvent."
				." It should be called on concreete event.");
		$_this = new static();
		if(!$log->topics[0]->eq(Hash::fromBin(static::abiEventID())))
			return null;

		$logCnt = count($log->topics);
		if($logCnt > 1) {
			$data = "";
			$logCnt--;
			for($i = 0; $i < $logCnt; $i++) {
				$data .= $log->topics[$i+1]->toBin();
			}
			foreach(ABIEncoder::decode(static::abiIndexedSignature(), $data)
						->unwrapToPhpFriendlyVals(null)AS $k => $value) {
				$_this["topic-".$k] = $value;
			}
		}
		if(!empty($log->data) && ($sig = static::abiDataSignature()) !== null) {
			foreach(ABIEncoder::decode($sig, $log->data)
						->unwrapToPhpFriendlyVals(static::abiDataTupleReplacements()) AS $k => $value) {
				$_this["data-".$k] = $value;
			}
		}
		return $_this;
	}
}