<?php
/** @file
 * The scanner.
 */

namespace QueryPath\CSS;

/**
 * Scanner for CSS selector parsing.
 *
 * This provides a simple scanner for traversing an input stream.
 *
 * @ingroup querypath_css
 */
final class Scanner
{

    public $is;
    public $value;
    public $token;

    public $recurse = false;
    public $it = 0;

    /**
     * Given a new input stream, tokenize the CSS selector string.
     *
     * @see InputStream
     * @param InputStream $in
     *  An input stream to be scanned.
     */
    public function __construct(InputStream $in)
    {
        $this->is = $in;
    }

    /**
     * Return the position of the reader in the string.
     */
    public function position(): int
    {
        return $this->is->position;
    }

    /**
     * See the next char without removing it from the stack.
     *
     * @return string
     * Returns the next character on the stack.
     */
    public function peek(): string
    {
        return $this->is->peek();
    }

    /**
     * Get the next token in the input stream.
     *
     * This sets the current token to the value of the next token in
     * the stream.
     *
     * @return int
     *  Returns an int value corresponding to one of the Token constants,
     *  or FALSE if the end of the string is reached. (Remember to use
     *  strong equality checking on FALSE, since 0 is a valid token id.)
     * @throws ParseException
     * @throws \QueryPath\Exception
     */
    public function nextToken(): int
    {
        $tok = -1;
        ++$this->it;
        if ($this->is->isEmpty()) {
            if ($this->recurse) {
                throw new \QueryPath\Exception('Recursion error detected at iteration ' . $this->it . '.');
            }
            //print "{$this->it}: All done\n";
            $this->recurse = true;
            $this->token = false;

            return false;
        }
        $ch = $this->is->consume();
        //print __FUNCTION__ . " Testing $ch.\n";
        if (ctype_space($ch)) {
            $this->value = ' '; // Collapse all WS to a space.
            $this->token = $tok = Token::WHITE;

            //$ch = $this->is->consume();
            return $tok;
        }

        if ($ch === '-' || $ch === '_' || ctype_alnum($ch)) {
            // It's a character
            $this->value = $ch; //strtolower($ch);
            $this->token = $tok = Token::CHAR;

            return $tok;
        }

        $this->value = $ch;

        switch ($ch) {
            case '*':
                $tok = Token::STAR;
                break;
            case chr(ord('>')):
                $tok = Token::RANGLE;
                break;
            case '.':
                $tok = Token::DOT;
                break;
            case '#':
                $tok = Token::OCTO;
                break;
            case '[':
                $tok = Token::LSQUARE;
                break;
            case ']':
                $tok = Token::RSQUARE;
                break;
            case ':':
                $tok = Token::COLON;
                break;
            case '(':
                $tok = Token::LPAREN;
                break;
            case ')':
                $tok = Token::RPAREN;
                break;
            case '+':
                $tok = Token::PLUS;
                break;
            case '~':
                $tok = Token::TILDE;
                break;
            case '=':
                $tok = Token::EQ;
                break;
            case '|':
                $tok = Token::PIPE;
                break;
            case ',':
                $tok = Token::COMMA;
                break;
            case chr(34):
                $tok = Token::QUOTE;
                break;
            case "'":
                $tok = Token::SQUOTE;
                break;
            case '\\':
                $tok = Token::BSLASH;
                break;
            case '^':
                $tok = Token::CARAT;
                break;
            case '$':
                $tok = Token::DOLLAR;
                break;
            case '@':
                $tok = Token::AT;
                break;
        }


        // Catch all characters that are legal within strings.
        if ($tok === -1) {
            // TODO: This should be UTF-8 compatible, but PHP doesn't
            // have a native UTF-8 string. Should we use external
            // mbstring library?

            $ord = ord($ch);
            // Characters in this pool are legal for use inside of
            // certain strings. Extended ASCII is used here, though I
            // Don't know if these are really legal.
            if (($ord >= 32 && $ord <= 126) || ($ord >= 128 && $ord <= 255)) {
                $tok = Token::STRING_LEGAL;
            } else {
                throw new ParseException('Illegal character found in stream: ' . $ord);
            }
        }

        $this->token = $tok;

        return $tok;
    }

    /**
     * Get a name string from the input stream.
     * A name string must be composed of
     * only characters defined in Token:char: -_a-zA-Z0-9
     */
    public function getNameString()
    {
        $buf = '';
        while ($this->token === Token::CHAR) {
            $buf .= $this->value;
            $this->nextToken();
        }

        return $buf;
    }

    /**
     * This gets a string with any legal 'string' characters.
     * See CSS Selectors specification, section 11, for the
     * definition of string.
     *
     * This will check for string1, string2, and the case where a
     * string is unquoted (Oddly absent from the "official" grammar,
     * though such strings are present as examples in the spec.)
     *
     * Note:
     * Though the grammar supplied by CSS 3 Selectors section 11 does not
     * address the contents of a pseudo-class value, the spec itself indicates
     * that a pseudo-class value is a "value between parenthesis" [6.6]. The
     * examples given use URLs among other things, making them closer to the
     * definition of 'string' than to 'name'. So we handle them here as strings.
     */
    public function getQuotedString()
    {
        if ($this->token === Token::QUOTE || $this->token === Token::SQUOTE || $this->token === Token::LPAREN) {
            $end = ($this->token === Token::LPAREN) ? Token::RPAREN : $this->token;
            $buf = '';
            $escape = false;

            $this->nextToken(); // Skip the opening quote/paren

            // The second conjunct is probably not necessary.
            while ($this->token !== false && $this->token > -1) {
                //print "Char: $this->value \n";
                if ($this->token == Token::BSLASH && !$escape) {
                    // XXX: The backslash (\) is removed here.
                    // Turn on escaping.
                    //$buf .= $this->value;
                    $escape = true;
                } elseif ($escape) {
                    // Turn off escaping
                    $buf .= $this->value;
                    $escape = false;
                } elseif ($this->token === $end) {
                    // At end of string; skip token and break.
                    $this->nextToken();
                    break;
                } else {
                    // Append char.
                    $buf .= $this->value;
                }
                $this->nextToken();
            }

            return $buf;
        }
    }

    // Get the contents inside of a pseudoClass().
    public function getPseudoClassString()
    {
        if ($this->token === Token::QUOTE || $this->token === Token::SQUOTE || $this->token === Token::LPAREN) {
            $end = ($this->token === Token::LPAREN) ? Token::RPAREN : $this->token;
            $buf = '';
            $escape = false;

            $this->nextToken(); // Skip the opening quote/paren

            // The second conjunct is probably not necessary.
            while ($this->token !== false && $this->token > -1) {
                //print "Char: $this->value \n";
                if ($this->token === Token::BSLASH && !$escape) {
                    // XXX: The backslash (\) is removed here.
                    // Turn on escaping.
                    //$buf .= $this->value;
                    $escape = true;
                } elseif ($escape) {
                    // Turn off escaping
                    $buf .= $this->value;
                    $escape = false;
                } // Allow nested pseudoclasses.
                elseif ($this->token === Token::LPAREN) {
                    $buf .= '(';
                    $buf .= $this->getPseudoClassString();
                    $buf .= ')';
                } elseif ($this->token === $end) {
                    // At end of string; skip token and break.
                    $this->nextToken();
                    break;
                } else {
                    // Append char.
                    $buf .= $this->value;
                }
                $this->nextToken();
            }

            return $buf;
        }
    }

    /**
     * Get a string from the input stream.
     * This is a convenience function for getting a string of
     * characters that are either alphanumber or whitespace. See
     * the Token::white and Token::char definitions.
     *
     * @deprecated This is not used anywhere in QueryPath.
     *//*
  public function getStringPlusWhitespace() {
    $buf = '';
    if($this->token === FALSE) {return '';}
    while ($this->token === Token::char || $this->token == Token::white) {
      $buf .= $this->value;
      $this->nextToken();
    }
    return $buf;
  }*/

}
