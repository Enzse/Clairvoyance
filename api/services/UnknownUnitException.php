<?php

namespace Services;

/** A spreadsheet row names a unit that doesn't exist (and creating it wasn't asked for). */
final class UnknownUnitException extends \DomainException
{
}
