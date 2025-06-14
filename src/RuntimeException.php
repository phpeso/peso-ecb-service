<?php

declare(strict_types=1);

namespace Peso\Services;

use Peso\Core\Exceptions\PesoException;

class RuntimeException extends \RuntimeException implements PesoException
{
}
