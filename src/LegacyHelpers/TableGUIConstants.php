<?php

declare(strict_types=1);

namespace srag\Plugins\Opencast\LegacyHelpers;

/**
 * @author     Fabian Schmid <fabian@sr.solutions>
 * @deprecated Do not use this interface anymore, it's only to make old tables run which used the srag/custominputguis library
 */
interface TableGUIConstants
{
    /**
     * @var int
     *
     * @deprecated
     */
    public const DEFAULT_FORMAT = 0;
    /**
     * @var int
     *
     * @deprecated
     */
    public const EXPORT_PDF = 3;
    /**
     * @var string
     *
     * @deprecated
     */
    public const LANG_MODULE = "";
    /**
     * @var string
     *
     * @abstract
     *
     * @deprecated
     */
    public const ROW_TEMPLATE = "";
}
