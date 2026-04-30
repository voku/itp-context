<?php

declare(strict_types=1);

namespace ItpContext\Context;

use ItpContext\Contract\RuleIdentifier;

enum PackageRules implements RuleIdentifier
{
    case FrameworkAgnostic;
    case CatalogByConvention;
    case TokenFirstDiscovery;
    case AgentFriendlyMarkdown;
}
