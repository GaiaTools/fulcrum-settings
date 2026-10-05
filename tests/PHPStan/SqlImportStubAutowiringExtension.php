<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\PHPStan;

use PHPStan\DependencyInjection\Configurator;
use PHPStan\PhpDoc\DefaultStubFilesProvider;
use ReflectionClass;

// PHPStan scopes its bundled Nette classes with a build-specific prefix.
// Resolve that namespace instead of depending on a particular build.
$namespace = (new ReflectionClass(Configurator::class))->getParentClass()->getNamespaceName();
$compilerExtension = substr($namespace, 0, strrpos($namespace, '\\')).'\\DI\\CompilerExtension';
if (! class_exists(__NAMESPACE__.'\\CompilerExtension', false)) {
    class_alias($compilerExtension, __NAMESPACE__.'\\CompilerExtension');
}

final class SqlImportStubAutowiringExtension extends CompilerExtension
{
    public function beforeCompile(): void
    {
        foreach ($this->getContainerBuilder()->getDefinitions() as $definition) {
            if ($definition->getType() === DefaultStubFilesProvider::class) {
                $definition->setAutowired([DefaultStubFilesProvider::class]);
            }
        }
    }
}
