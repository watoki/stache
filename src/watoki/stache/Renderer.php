<?php
namespace watoki\stache;

use watoki\collections\Liste;
use watoki\collections\Map;
use watoki\collections\Set;

class Renderer {

    public static $CLASS = __CLASS__;

    /**
     * @var string
     */
    private $template;

    public function __construct($template) {
        $this->template = $template;
    }

    /**
     * @param array|object $model
     * @return string
     */
    public function render($model) {
        return $this->parse($this->template, array($model));
    }

    /**
     * @param string $content
     * @param array $views
     *
     * @throws ParsingException
     * @return string
     */
    private function parse($content, $views) {
        $matches = array();

        while (true) {
            $count = preg_match('/\{\{([^}]+)\}\}/', $content, $matches, PREG_OFFSET_CAPTURE);

            if ($count == 0) {
                return $content;
            }

            $tagName = $matches[1][0];
            $tagLength = strlen($matches[0][0]);
            $pos = $matches[0][1];

            $before = substr($content, 0, $pos);
            $replace = '';
            $after = substr($content, $pos + $tagLength);

            $scopes = explode('.', $tagName);
            $tagName = array_pop($scopes);

            $view = $views[0];
            $nextViews = $views;

            $property = $this->getProperty($view, $tagName, $nextViews);

            $firstLetter = substr($tagName, 0, 1);

            if ($firstLetter == '#' || $firstLetter == '^') {
                $tagName = substr($tagName, 1);
                $end = $this->findMatchingEndTag($tagName, $after);

                if ($end === false) {
                    throw new ParsingException(
                        'No closing tag found for tag ' . $matches[0][0] . ' at pos ' . $pos . ' in template '
                                . $content);
                }

                $inside = substr($after, 0, $end);
                $after = $end + $tagLength == strlen($after) ? '' : substr($after, $end + $tagLength);

                $property = $this->getProperty($view, $tagName, $nextViews);

                if ($firstLetter == '#') {
                    if ($this->isCallable($view, $tagName)) {
                        $replace = $this->invoke($view, $tagName, $this->parse($inside, $nextViews));
                    } else if ($property) {
                        if (is_array($property)
                                || $property instanceof Liste
                                || $property instanceof Set
                        ) {
                            foreach ($property as $item) {
                                $itemViews = $nextViews;
                                array_unshift($itemViews, $item);
                                $replace .= $this->parse($inside, $itemViews);
                            }
                        } else if (is_object($property)) {
                            array_unshift($nextViews, $property);
                            $replace = $this->parse($inside, $nextViews);
                        } else if ($property) {
                            $replace = $inside;
                        }
                    }
                } else if (!$property) {
                    $replace = $inside;
                }
            } else if ($property && is_object($property) && method_exists($property, '__toString')) {
                $replace = $property->__toString();
            } else if ($this->isCallable($view, $tagName)) {
                $replace = $this->invoke($view, $tagName);
            } else {
                $replace = $property;
            }

            $content = $before . $replace . $this->parse($after, $views);
        }

        return null;
    }

    private function findMatchingEndTag($tagName, $in) {
        $openTags = 1;
        $totalPos = 0;
        $length = 0;

        while ($openTags > 0) {
            $matches = array();
            $found = preg_match('/\{\{([#\/])' . $tagName . '\}\}/', $in, $matches, PREG_OFFSET_CAPTURE);

            if ($found == 0) {
                return false;
            }

            $type = $matches[1][0];
            $pos = $matches[0][1];
            $length = strlen($matches[0][0]);

            $totalPos += $pos + $length;

            if ($type == '#') {
                $openTags++;
            } else {
                $openTags--;
            }

            $in = substr($in, $pos + $length);
        }

        return $totalPos - $length;
    }

    private function isCallable($view, $tagName) {
        if (is_array($view)) {
            return isset($view[$tagName]) && is_callable($view[$tagName]);
        } else if (is_object($view)) {
            return method_exists($view, $tagName) || isset($view->$tagName) && is_callable($view->$tagName);
        } else {
            return false;
        }
    }

    private function getProperty($view, $tagName, &$views) {
        if ($tagName == 'parent') {
            array_shift($views);
            return $views[0];
        } else if ($tagName == 'this') {
            return $view;
        } else if (is_array($view)) {
            return isset($view[$tagName]) ? $view[$tagName] : null;
        } else if ($view instanceof Map) {
            /** @var $view Map */
            return $view->has($tagName) ? $view->get($tagName) : null;
        } else if (is_object($view)) {
            return isset($view->$tagName) ? $view->$tagName : null;
        } else {
            return null;
        }
    }

    private function invoke($view, $tagName, $arg = null) {
        if (is_array($view)) {
            if ($arg) {
                return $view[$tagName]($arg);
            } else {
                return $view[$tagName]();
            }
        } else if (is_object($view)) {
            if (method_exists($view, $tagName)) {
                if ($arg) {
                    return $view->{$tagName}($arg);
                } else {
                    return $view->{$tagName}();
                }
            } else if (isset($view->$tagName)) {
                /** @var $callback \Closure */
                $callback = $view->$tagName;
                if ($arg) {
                    return $callback($arg);
                } else {
                    return $callback();
                }
            }
        }
        return '';
    }

}

