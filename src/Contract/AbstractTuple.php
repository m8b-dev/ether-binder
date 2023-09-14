<?php

namespace M8B\EtherBinder\Contract;

/**
 * Class that is catch-all for all abi generated bindings. On its own it has only ArrayAccess store for tuple data,
 * but its main purpose is typing.
 *
 * @author DubbaThony
 */
abstract class AbstractTuple extends AbstractArrayAccess
{}
