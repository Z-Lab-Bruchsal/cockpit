<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A reservation could not be made; the message is meant to be shown to the visitor.
 */
class ReservationException extends RuntimeException {}
