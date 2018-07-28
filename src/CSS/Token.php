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
    public const char    = 0;
    public const star    = 1;
    public const rangle  = 2;
    public const dot     = 3;
    public const octo    = 4;
    public const rsquare = 5;
    public const lsquare = 6;
    public const colon   = 7;
    public const rparen  = 8;
    public const lparen  = 9;
    public const plus    = 10;
    public const tilde   = 11;
    public const eq      = 12;
    public const pipe    = 13;
    public const comma   = 14;
    public const white   = 15;
    public const quote   = 16;
    public const squote  = 17;
    public const bslash  = 18;
    public const carat   = 19;
    public const dollar  = 20;
    public const at      = 21; // This is not in the spec. Apparently, old broken CSS uses it.

    // In legal range for string.
    const stringLegal = 99;

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
        } elseif ($const_int === 99) {
            return 'a legal non-alphanumeric character';
        } elseif ($const_int == false) {
            return 'end of file';
        }

        return sprintf('illegal character (%s)', $const_int);
    }
}
