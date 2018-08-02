<?php
/** @file
 * Parser tokens.
 */

namespace QueryPath\CSS;

/**
 * Tokens for CSS.
 * This class defines the recognized tokens for the parser, and also
 * provides utility functions for error reporting.
 *
 * @ingroup querypath_css
 */
final class Token
{
    public const CHAR    = 0x0;
    public const STAR    = 0x1;
    public const RANGLE  = 0x2;
    public const DOT     = 0x3;
    public const OCTO    = 0x4;
    public const RSQUARE = 0x5;
    public const LSQUARE = 0x6;
    public const COLON   = 0x7;
    public const RPAREN  = 0x8;
    public const LPAREN  = 0x9;
    public const PLUS    = 0xA;
    public const TILDE   = 0xB;
    public const EQ      = 0xC;
    public const PIPE    = 0xD;
    public const COMMA   = 0xE;
    public const WHITE   = 0xF;
    public const QUOTE   = 0x10;
    public const SQUOTE  = 0x11;
    public const BSLASH  = 0x12;
    public const CARAT   = 0x13;
    public const DOLLAR  = 0x14;
    public const AT      = 0x15; // This is not in the spec. Apparently, old broken CSS uses it.

    // In legal range for string.
    public const STRING_LEGAL = 0x63;

    /**
     * Get a name for a given constant. Used for error handling.
     */
    public static function name($const_int)
    {
        $a = [
            'character',
            'star',
            'right angle bracket',
            'dot',
            'octothorp',
            'right square bracket',
            'left square bracket',
            'colon',
            'right parenthesis',
            'left parenthesis',
            'plus',
            'tilde',
            'equals',
            'vertical bar',
            'comma',
            'space',
            'quote',
            'single quote',
            'backslash',
            'carat',
            'dollar',
            'at',
        ];

        if (isset($a[$const_int]) && is_numeric($const_int)) {
            return $a[$const_int];
        }

        if ($const_int === self::STRING_LEGAL) {
            return 'a legal non-alphanumeric character';
        }

        if ($const_int === false) {
            return 'end of file';
        }

        return sprintf('illegal character (%s)', $const_int);
    }
}
