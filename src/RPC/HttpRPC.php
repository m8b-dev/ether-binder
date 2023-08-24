<?php

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

namespace M8B\EtherBinder\RPC;

use M8B\EtherBinder\Exceptions\InvalidURLException;
use M8B\EtherBinder\Exceptions\RPCGeneralException;
use M8B\EtherBinder\Exceptions\RPCInvalidResponseParamException;
use M8B\EtherBinder\Exceptions\RPCNotFoundException;

class HttpRPC extends AbstractRPC
{
	private int $id = 1;
	private array $headers = [];
	private \CurlHandle $ch;

	public function __construct(string $url, array $extraHeaders = [])
	{
		if(str_starts_with($url, "ws"))
			throw new InvalidURLException("ws and wss protocols are not supported yet");
		if(!filter_var($url, FILTER_VALIDATE_URL))
			throw new InvalidURLException("provided url is not valid");
		$this->headers = $extraHeaders;
		$this->headers["Content-Type"] = "application/json";
		$this->ch = curl_init($url);
	}

	public function __destruct() {
		curl_close($this->ch);
	}

	public function raw(string $method, ?array $params = null): array
	{
		$headers = [];
		foreach($this->headers AS $name => $value) {
			$headers[] = $name . ": ". $value;
		}
		curl_setopt_array($this->ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode(array(
				"jsonrpc" => "2.0",
				"method" => $method,
				"params" => $params ?? [],
				"id" => $this->id
			))
		));
		$this->id++;
		$resp = curl_exec($this->ch);
		if(!$resp)
			throw new RPCGeneralException("didn't receive response");
		try {
			$d = json_decode($resp, true, JSON_THROW_ON_ERROR);
			if(!is_array($d)) { // can happen if RPC responds with error as "pure" string. The json_decode will just return string.
				throw new RPCGeneralException("failed to parse json: did not receive json object, got " . $resp);
			}
			return $d;
		} catch(\JsonException $e) {
			throw new RPCGeneralException("failed to parse json", 0, $e);
		}
	}
}
