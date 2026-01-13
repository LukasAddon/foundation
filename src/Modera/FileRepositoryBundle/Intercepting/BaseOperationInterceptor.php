<?php

namespace Modera\FileRepositoryBundle\Intercepting;

use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Modera\FileRepositoryBundle\ThumbnailsGenerator\AlternativeFileTrait;

/**
 * @copyright 2016 Modera Foundation
 */
class BaseOperationInterceptor implements OperationInterceptorInterface
{
    public function beforePut(\SplFileInfo $file, Repository $repository, array $context = []): void
    {
    }

    public function onPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository, array $context = []): void
    {
    }

    public function afterPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository, array $context = []): void
    {
    }

    protected function isAlternative(\SplFileInfo $file): bool
    {
        return \in_array(AlternativeFileTrait::class, \class_uses(\get_class($file)));
    }
}
