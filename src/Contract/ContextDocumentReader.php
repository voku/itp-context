<?php

declare(strict_types=1);

namespace ItpContext\Contract;

use ItpContext\Model\ContextDocument;

interface ContextDocumentReader
{
    /**
     * @return list<ContextDocument>
     */
    public function read(string $filePath): array;
}
