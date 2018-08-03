<?php
/**
 * @file
 * HTML entity utilities.
 */

namespace QueryPath;

/**
 * Perform various tasks on HTML/XML entities.
 *
 * @ingroup querypath_util
 */
class Entities implements EntitiesContract
{

    /**
     * This is three regexes wrapped into 1. The | divides them.
     * 1: Match any char-based entity. This will go in $matches[1]
     * 2: Match any num-based entity. This will go in $matches[2]
     * 3: Match any hex-based entry. This will go in $matches[3]
     * 4: Match any ampersand that is not an entity. This goes in $matches[4]
     *    This last rule will only match if one of the previous two has not already
     *    matched.
     * XXX: Are octal encodings for entities acceptable?
     */
    //protected static $regex = '/&([\w]+);|&#([\d]+);|&([\w]*[\s$]+)/m';
    protected static $regex = '/&([\w]+);|&#([\d]+);|&#(x[0-9a-fA-F]+);|(&)/m';

    /**
     * Replace all entities.
     * This will scan a string and will attempt to replace all
     * entities with their numeric equivalent. This will not work
     * with specialized entities.
     *
     * @param string $string
     *  The string to perform replacements on.
     * @return string
     *  Returns a string that is similar to the original one, but with
     *  all entity replacements made.
     */
    public static function replaceAllEntities(string $string): string
    {
        return preg_replace_callback(self::$regex, '\QueryPath\Entities::doReplacement', $string);
    }

    /**
     * Callback for processing replacements.
     *
     * @param array $matches
     *  The regular expression replacement array.
     * @return string
     */
    protected static function doReplacement($matches): string
    {
        // See how the regex above works out.

        // From count, we can tell whether we got a
        // char, num, or bare ampersand.
        $count = count($matches);
        switch ($count) {
            case 2:
                // We have a character entity
                return '&#' . self::replaceEntity($matches[1]) . ';';
            case 3:
            case 4:
                // we have a numeric entity
                return '&#' . $matches[$count - 1] . ';';
            case 5:
                // We have an unescaped ampersand.
                return '&#38;';
        }

        return '';
    }

    /**
     * Lookup an entity string's numeric equivalent.
     *
     * @param string $entity
     *  The entity whose numeric value is needed.
     * @return int
     *  The integer value corresponding to the entity.
     * @author Matt Butcher
     * @author Ryan Mahoney
     */
    public static function replaceEntity(string $entity): int
    {
        return self::ENTITIES[$entity];
    }
}

