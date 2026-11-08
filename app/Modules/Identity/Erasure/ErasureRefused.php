<?php

declare(strict_types=1);

namespace App\Modules\Identity\Erasure;

use RuntimeException;

/**
 * A listener to DeletingAccount refused to let AccountEraser erase the
 * account. The message is the listener's.
 */
class ErasureRefused extends RuntimeException {}
