<?php

namespace App\Tenancy;

use RuntimeException;

/** Se lanza cuando algo intenta cruzar datos entre gimnasios. */
class TenantViolation extends RuntimeException {}
