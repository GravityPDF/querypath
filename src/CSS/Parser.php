<?php
/**
 * @file
 *
 * The CSS parser
 */

namespace QueryPath\CSS;

use QueryPath\Exception;

/**
 * Parse a CSS selector.
 *
 * In CSS, a selector is used to identify which element or elements
 * in a DOM are being selected for the application of a particular style.
 * Effectively, selectors function as a query language for a structured
 * document -- almost always HTML or XML.
 *
 * This class provides an event-based parser for CSS selectors. It can be
 * used, for example, as a basis for writing a DOM query engine based on
 * CSS.
 *
 * @ingroup querypath_css
 */
class Parser
{
    protected $scanner;
    protected $buffer = '';
    protected $handler;
    private $strict = false;

    protected $DEBUG = false;

    /**
     * Construct a new CSS parser object. This will attempt to
     * parse the string as a CSS selector. As it parses, it will
     * send events to the EventHandler implementation.
     *
     * @param string $string
     * @param EventHandler $handler
     */
    public function __construct(string $string, EventHandler $handler)
    {
        $this->originalString = $string;
        $is = new InputStream($string);
        $this->scanner = new Scanner($is);
        $this->handler = $handler;
    }

    /**
     * Parse the selector.
     *
     * This begins an event-based parsing process that will
     * fire events as the selector is handled. A EventHandler
     * implementation will be responsible for handling the events.
     *
     * @throws ParseException
     * @throws Exception
     */
    public function parse(): void
    {
        $this->scanner->nextToken();

        while ($this->scanner->token !== false) {
            // Primitive recursion detection.
            $position = $this->scanner->position();

            if ($this->DEBUG) {
                echo 'PARSE ' . $this->scanner->token . PHP_EOL;
            }
            $this->selector();

            $finalPosition = $this->scanner->position();
            if ($this->scanner->token !== false && $finalPosition === $position) {
                // If we get here, then the scanner did not pop a single character
                // off of the input stream during a full run of the parser, which
                // means that the current input does not match any recognizable
                // pattern.
                throw new ParseException('CSS selector is not well formed.');
            }
        }
    }

    /**
     * A restricted parser that can only parse simple selectors.
     * The pseudoClass handler for this parser will throw an
     * exception if it encounters a pseudo-element or the
     * negation pseudo-class.
     *
     * @deprecated This is not used anywhere in QueryPath and
     *  may be removed.
     *//*
  public function parseSimpleSelector() {
    while ($this->scanner->token !== FALSE) {
      if ($this->DEBUG) print "SIMPLE SELECTOR\n";
      $this->allElements();
      $this->elementName();
      $this->elementClass();
      $this->elementID();
      $this->pseudoClass(TRUE); // Operate in restricted mode.
      $this->attribute();

      // TODO: Need to add failure conditions here.
    }
  }*/

    /**
     * Handle an entire CSS selector.
     *
     * @throws ParseException
     * @throws Exception
     */
    private function selector(): void
    {
        if ($this->DEBUG) {
            print 'SELECTOR' . $this->scanner->position() . PHP_EOL;
        }

        $this->consumeWhitespace(); // Remove leading whitespace
        $this->simpleSelectors();
        $this->combinator();
    }

    /**
     * Consume whitespace and return a count of the number of whitespace consumed.
     *
     * @throws \QueryPath\CSS\ParseException
     * @throws Exception
     */
    private function consumeWhitespace(): int
    {
        if ($this->DEBUG) {
            echo 'CONSUME WHITESPACE' . PHP_EOL;
        }

        $white = 0;
        while ($this->scanner->token === Token::WHITE) {
            $this->scanner->nextToken();
            ++$white;
        }

        return $white;
    }

    /**
     * Handle one of the five combinators: '>', '+', ' ', '~', and ','.
     * This will call the appropriate event handlers.
     *
     * @see EventHandler::directDescendant(),
     * @see EventHandler::adjacent(),
     * @see EventHandler::anyDescendant(),
     * @see EventHandler::anotherSelector().
     * @throws ParseException
     * @throws \QueryPath\Exception
     */
    private function combinator(): void
    {
        if ($this->DEBUG) {
            echo 'COMBINATOR' . PHP_EOL;
        }
        /*
         * Problem: ' ' and ' > ' are both valid combinators.
         * So we have to track whitespace consumption to see
         * if we are hitting the ' ' combinator or if the
         * selector just has whitespace padding another combinator.
         */

        // Flag to indicate that post-checks need doing
        $inCombinator = false;
        $white = $this->consumeWhitespace();
        $t = $this->scanner->token;

        if ($t === Token::RANGLE) {
            $this->handler->directDescendant();
            $this->scanner->nextToken();
            $inCombinator = true;
            //$this->simpleSelectors();
        } elseif ($t === Token::PLUS) {
            $this->handler->adjacent();
            $this->scanner->nextToken();
            $inCombinator = true;
            //$this->simpleSelectors();
        } elseif ($t === Token::COMMA) {
            $this->handler->anotherSelector();
            $this->scanner->nextToken();
            $inCombinator = true;
            //$this->scanner->selectors();
        } elseif ($t === Token::TILDE) {
            $this->handler->sibling();
            $this->scanner->nextToken();
            $inCombinator = true;
        }

        // Check that we don't get two combinators in a row.
        if ($inCombinator) {
            if ($this->DEBUG) {
                print 'COMBINATOR: ' . Token::name($t) . "\n";
            }
            $this->consumeWhitespace();
            if ($this->isCombinator($this->scanner->token)) {
                throw new ParseException('Illegal combinator: Cannot have two combinators in sequence.');
            }
        } // Check to see if we have whitespace combinator:
        elseif ($white > 0) {
            if ($this->DEBUG) {
                echo 'COMBINATOR: any descendant' . PHP_EOL;
            }
            $this->handler->anyDescendant();
        } else {
            if ($this->DEBUG) {
                echo 'COMBINATOR: no combinator found.' . PHP_EOL;
            }
        }
    }

    /**
     * Check if the token is a combinator.
     *
     * @param int $tok
     * @return bool
     */
    private function isCombinator(int $tok): bool
    {
        return in_array($tok, [Token::PLUS, Token::RANGLE, Token::COMMA, Token::TILDE], true);
    }

    /**
     * Handle a simple selector.
     *
     * @throws ParseException
     */
    private function simpleSelectors(): void
    {
        if ($this->DEBUG) {
            print 'SIMPLE SELECTOR' . PHP_EOL;
        }
        $this->allElements();
        $this->elementName();
        $this->elementClass();
        $this->elementID();
        $this->pseudoClass();
        $this->attribute();
    }

    /**
     * Handles CSS ID selectors.
     * This will call EventHandler::elementID().
     *
     * @throws \QueryPath\CSS\ParseException
     * @throws Exception
     */
    private function elementID(): void
    {
        if ($this->DEBUG) {
            echo 'ELEMENT ID' . PHP_EOL;
        }

        if ($this->scanner->token === Token::OCTO) {
            $this->scanner->nextToken();
            if ($this->scanner->token !== Token::CHAR) {
                throw new ParseException("Expected string after #");
            }
            $id = $this->scanner->getNameString();
            $this->handler->elementID($id);
        }
    }

    /**
     * Handles CSS class selectors.
     * This will call the EventHandler::elementClass() method.
     */
    private function elementClass(): void
    {
        if ($this->DEBUG) {
            print 'ELEMENT CLASS' . PHP_EOL;
        }
        if ($this->scanner->token == Token::DOT) {
            $this->scanner->nextToken();
            $this->consumeWhitespace(); // We're very fault tolerent. This should prob through error.
            $cssClass = $this->scanner->getNameString();
            $this->handler->elementClass($cssClass);
        }
    }

    /**
     * Handle a pseudo-class and pseudo-element.
     *
     * CSS 3 selectors support separate pseudo-elements, using :: instead
     * of : for separator. This is now supported, and calls the pseudoElement
     * handler, EventHandler::pseudoElement().
     *
     * This will call EventHandler::pseudoClass() when a
     * pseudo-class is parsed.
     *
     * @throws ParseException
     * @throws Exception
     */
    private function pseudoClass($restricted = false): void
    {
        if ($this->DEBUG) {
            echo 'PSEUDO-CLASS' . PHP_EOL;
        }
        if ($this->scanner->token === Token::COLON) {
            // Check for CSS 3 pseudo element:
            $isPseudoElement = false;
            if ($this->scanner->nextToken() === Token::COLON) {
                $isPseudoElement = true;
                $this->scanner->nextToken();
            }

            $name = $this->scanner->getNameString();
            if ($restricted && $name === 'not') {
                throw new ParseException("The 'not' pseudo-class is illegal in this context.");
            }

            $value = NULL;
            if ($this->scanner->token === Token::LPAREN) {
                if ($isPseudoElement) {
                    throw new ParseException('Illegal left paren. Pseudo-Element cannot have arguments.');
                }
                $value = $this->pseudoClassValue();
            }

            // FIXME: This should throw errors when pseudo element has values.
            if ($isPseudoElement) {
                if ($restricted) {
                    throw new ParseException('Pseudo-Elements are illegal in this context.');
                }
                $this->handler->pseudoElement($name);
                $this->consumeWhitespace();

                // Per the spec, pseudo-elements must be the last items in a selector, so we
                // check to make sure that we are either at the end of the stream or that a
                // new selector is starting. Only one pseudo-element is allowed per selector.
                if ($this->scanner->token !== false && $this->scanner->token !== Token::COMMA) {
                    throw new ParseException('A Pseudo-Element must be the last item in a selector.');
                }
            } else {
                $this->handler->pseudoClass($name, $value);
            }
        }
    }

    /**
     * Get the value of a pseudo-classes.
     *
     * @return string
     *  Returns the value found from a pseudo-class.
     *
     * @todo Pseudoclasses can be passed pseudo-elements and
     *  other pseudo-classes as values, which means :pseudo(::pseudo)
     *  is legal.
     */
    private function pseudoClassValue()
    {
        if ($this->scanner->token === Token::LPAREN) {
            $buf = '';

            // For now, just leave pseudoClass value vague.
            /*
            // We have to peek to see if next char is a colon because
            // pseudo-classes and pseudo-elements are legal strings here.
            print $this->scanner->peek();
            if ($this->scanner->peek() == ':') {
              print "Is pseudo\n";
              $this->scanner->nextToken();

              // Pseudo class
              if ($this->scanner->token == Token::colon) {
                $buf .= ':';
                $this->scanner->nextToken();
                // Pseudo element
                if ($this->scanner->token == Token::colon) {
                  $buf .= ':';
                  $this->scanner->nextToken();
                }
                // Ident
                $buf .= $this->scanner->getNameString();
              }
            }
            else {
              print "fetching string.\n";
              $buf .= $this->scanner->getQuotedString();
              if ($this->scanner->token != Token::rparen) {
                $this->throwError(Token::rparen, $this->scanner->token);
              }
              $this->scanner->nextToken();
            }
            return $buf;
            */
            //$buf .= $this->scanner->getQuotedString();
            $buf .= $this->scanner->getPseudoClassString();

            return $buf;
        }
    }

    /**
     * Handle element names.
     * This will call the EventHandler::elementName().
     *
     * This handles:
     * <code>
     *  name (EventHandler::element())
     *  |name (EventHandler::element())
     *  ns|name (EventHandler::elementNS())
     *  ns|* (EventHandler::elementNS())
     * </code>
     */
    private function elementName()
    {
        if ($this->DEBUG) {
            print "ELEMENT NAME\n";
        }
        if ($this->scanner->token === Token::PIPE) {
            // We have '|name', which is equiv to 'name'
            $this->scanner->nextToken();
            $this->consumeWhitespace();
            $elementName = $this->scanner->getNameString();
            $this->handler->element($elementName);
        } elseif ($this->scanner->token === Token::CHAR) {
            $elementName = $this->scanner->getNameString();
            if ($this->scanner->token == Token::PIPE) {
                // Get ns|name
                $elementNS = $elementName;
                $this->scanner->nextToken();
                $this->consumeWhitespace();
                if ($this->scanner->token === Token::STAR) {
                    // We have ns|*
                    $this->handler->anyElementInNS($elementNS);
                    $this->scanner->nextToken();
                } elseif ($this->scanner->token !== Token::CHAR) {
                    $this->throwError(Token::CHAR, $this->scanner->token);
                } else {
                    $elementName = $this->scanner->getNameString();
                    // We have ns|name
                    $this->handler->elementNS($elementName, $elementNS);
                }

            } else {
                $this->handler->element($elementName);
            }
        }
    }

    /**
     * Check for all elements designators. Due to the new CSS 3 namespace
     * support, this is slightly more complicated, now, as it handles
     * the *|name and *|* cases as well as *.
     *
     * Calls EventHandler::anyElement() or EventHandler::elementName().
     */
    private function allElements()
    {
        if ($this->scanner->token === Token::STAR) {
            $this->scanner->nextToken();
            if ($this->scanner->token === Token::PIPE) {
                $this->scanner->nextToken();
                if ($this->scanner->token === Token::STAR) {
                    // We got *|*. According to spec, this requires
                    // that the element has a namespace, so we pass it on
                    // to the handler:
                    $this->scanner->nextToken();
                    $this->handler->anyElementInNS('*');
                } else {
                    // We got *|name, which means the name MUST be in a namespce,
                    // so we pass this off to elementNameNS().
                    $name = $this->scanner->getNameString();
                    $this->handler->elementNS($name, '*');
                }
            } else {
                $this->handler->anyElement();
            }
        }
    }

    /**
     * Handler an attribute.
     * An attribute can be in one of two forms:
     * <code>[attrName]</code>
     * or
     * <code>[attrName="AttrValue"]</code>
     *
     * This may call the following event handlers: EventHandler::attribute().
     *
     * @throws \QueryPath\CSS\ParseException
     * @throws Exception
     */
    private function attribute()
    {
        if ($this->scanner->token === Token::LSQUARE) {
            $attrVal = $op = $ns = NULL;

            $this->scanner->nextToken();
            $this->consumeWhitespace();

            if ($this->scanner->token === Token::AT) {
                if ($this->strict) {
                    throw new ParseException('The @ is illegal in attributes.');
                }

                $this->scanner->nextToken();
                $this->consumeWhitespace();
            }

            if ($this->scanner->token === Token::STAR) {
                // Global namespace... requires that attr be prefixed,
                // so we pass this on to a namespace handler.
                $ns = '*';
                $this->scanner->nextToken();
            }
            if ($this->scanner->token === Token::PIPE) {
                // Skip this. It's a global namespace.
                $this->scanner->nextToken();
                $this->consumeWhitespace();
            }

            $attrName = $this->scanner->getNameString();
            $this->consumeWhitespace();

            // Check for namespace attribute: ns|attr. We have to peek() to make
            // sure that we haven't hit the |= operator, which looks the same.
            if ($this->scanner->token === Token::PIPE && $this->scanner->peek() !== '=') {
                // We have a namespaced attribute.
                $ns = $attrName;
                $this->scanner->nextToken();
                $attrName = $this->scanner->getNameString();
                $this->consumeWhitespace();
            }

            // Note: We require that operators do not have spaces
            // between characters, e.g. ~= , not ~ =.

            // Get the operator:
            switch ($this->scanner->token) {
                case Token::EQ:
                    $this->consumeWhitespace();
                    $op = EventHandler::IS_EXACTLY;
                    break;
                case Token::TILDE:
                    if ($this->scanner->nextToken() !== Token::EQ) {
                        $this->throwError(Token::EQ, $this->scanner->token);
                    }
                    $op = EventHandler::CONTAINS_WITH_SPACE;
                    break;
                case Token::PIPE:
                    if ($this->scanner->nextToken() !== Token::EQ) {
                        $this->throwError(Token::EQ, $this->scanner->token);
                    }
                    $op = EventHandler::CONTAINS_WITH_HYPHEN;
                    break;
                case Token::STAR:
                    if ($this->scanner->nextToken() !== Token::EQ) {
                        $this->throwError(Token::EQ, $this->scanner->token);
                    }
                    $op = EventHandler::CONTAINS_IN_STRING;
                    break;
                case Token::DOLLAR;
                    if ($this->scanner->nextToken() !== Token::EQ) {
                        $this->throwError(Token::EQ, $this->scanner->token);
                    }
                    $op = EventHandler::ENDS_WITH;
                    break;
                case Token::CARAT:
                    if ($this->scanner->nextToken() !== Token::EQ) {
                        $this->throwError(Token::EQ, $this->scanner->token);
                    }
                    $op = EventHandler::BEGINS_WITH;
                    break;
            }

            if (isset($op)) {
                // Consume '=' and go on.
                $this->scanner->nextToken();
                $this->consumeWhitespace();

                // So... here we have a problem. The grammer suggests that the
                // value here is String1 or String2, both of which are enclosed
                // in quotes of some sort, and both of which allow lots of special
                // characters. But the spec itself includes examples like this:
                //   [lang=fr]
                // So some bareword support is assumed. To get around this, we assume
                // that bare words follow the NAME rules, while quoted strings follow
                // the String1/String2 rules.

                if ($this->scanner->token === Token::QUOTE || $this->scanner->token === Token::SQUOTE) {
                    $attrVal = $this->scanner->getQuotedString();
                } else {
                    $attrVal = $this->scanner->getNameString();
                }

                if ($this->DEBUG) {
                    print "ATTR: $attrVal AND OP: $op\n";
                }
            }

            $this->consumeWhitespace();

            if ($this->scanner->token !== Token::RSQUARE) {
                $this->throwError(Token::RSQUARE, $this->scanner->token);
            }

            if (isset($ns)) {
                $this->handler->attributeNS($attrName, $ns, $attrVal, $op);
            } elseif (isset($attrVal)) {
                $this->handler->attribute($attrName, $attrVal, $op);
            } else {
                $this->handler->attribute($attrName);
            }
            $this->scanner->nextToken();
        }
    }

    /**
     * Utility for throwing a consistantly-formatted parse error.
     */
    private function throwError($expected, $got)
    {
        $filter = sprintf('Expected %s, got %s', Token::name($expected), Token::name($got));
        throw new ParseException($filter);
    }

    /**
     * @return Scanner
     */
    public function getScanner(): Scanner
    {
        return $this->scanner;
    }

}

