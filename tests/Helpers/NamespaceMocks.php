<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Services;

if (! function_exists('GaiaTools\FulcrumSettings\Services\class_exists')) {
    function class_exists($class, $autoload = true)
    {
        if ($class === 'Laravel\Telescope\Telescope' && isset($GLOBALS['mock_telescope_missing'])) {
            return false;
        }

        return \class_exists($class, $autoload);
    }
}
