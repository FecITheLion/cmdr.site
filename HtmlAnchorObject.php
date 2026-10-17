<?php

class HtmlAnchorObject
{
    private $value = null;
    private $sender = null;
    private $anchor = null;

    // Public read/write text (becomes {context})
    public $text = '';

    // Resolved crawlable URL that ends up in the href attribute
    private $resolvedHref = '';

    // Optional global map of id => crawlable URL
    // Example: $GLOBALS['hrefMap'] = ['ttest' => 'https://example.com/page'];
    private static $hrefMapKey = 'hrefMap';

    // Template parts
    protected $heading  = '';
    protected $leadhead = '';
    protected $tagname  = 'a';
    protected $leadtail = '';
    protected $head     = '';
    protected $context  = '';
    protected $tail     = '';
    protected $tailhead = '/';
    protected $tailtail = '';
    protected $tailing  = '';

    private const TEMPLATE = '{heading}<{leadhead}{tagname}{leadtail}>{head}{context}{tail}<{tailhead}{tagname}{tailtail}>{tailing}';

    public function __construct($value = null)
    {
        if (!is_null($value)) {
            $this->sender = $value;
            $this->anchor = $this->edify($this->sender);
        }
    }

    /**
     * Resolve a value that is either:
     * - a crawlable URL, or
     * - an id that looks up a crawlable URL in the global map
     * The resolved crawlable URL becomes the real href.
     */
    public function href($value): self
    {
        $resolved = $this->resolveHref($value);
        if ($resolved !== null) {
            $this->resolvedHref = $resolved;
            $this->leadtail = ' href="' . htmlspecialchars($resolved, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $this;
    }

    private function resolveHref($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        // Direct crawlable URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // ID lookup in global map
        $map = $GLOBALS[self::$hrefMapKey] ?? null;
        if (is_array($map) && isset($map[$value]) && is_string($map[$value])) {
            $candidate = $map[$value];
            if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                return $candidate;
            }
        }

        // Fallback: treat the raw extracted token as a relative path
        return $value;
    }

    private function edify($value)
    {
        $extracted = $this->html_anchor($value);
        $this->anchor = $extracted;

        if (is_string($extracted) && $extracted !== '') {
            // Let the href() method do the resolution
            $this->href($extracted);
        }

        $this->context = $this->text;
        return $extracted;
    }

    private function bottom_anchor(SplFixedArray $anchor)
    {
        if ($anchor !== null) {
            list($value, $solidus, $tagname) = $anchor;
            $color = new SplFixedArray(8);
            $color[0] = $value;
            $color[1] = $solidus;
            $color[2] = $tagname;
            $solve = 4;
            $color[3] = $this->intra_anchor($color, $solve);

            $marker = $color[3]; // "/a/"
            $pos = strpos((string)$value, $marker);
            if ($pos !== false) {
                $extracted = substr((string)$value, $pos + strlen($marker));
                if ($extracted !== false && $extracted !== '') {
                    return $extracted;
                }
            }
        }
        return $anchor;
    }

    private function anchor_tag_input($value, $solidus = '/', $tagname = 'a')
    {
        if (is_string($value)) {
            $value = [$solidus => $value];
        }
        if (!is_array($value) && !$value instanceof ArrayAccess) {
            return null;
        }

        $color = new SplFixedArray(3);
        $color[1] = $solidus;
        $color[2] = $tagname;
        if (isset($value[$solidus])) {
            $color[0] = $value[$solidus];
            return $color;
        }
        return null;
    }

    private function html_anchor($value = null)
    {
        if (is_null($value)) {
            return null;
        }
        $this->value = SplFixedArray::fromArray(['/', 'a']);
        $solve = 0;
        $processed = $this->anchor_tag_input(
            $value,
            $this->value[$solve],
            $this->value[++$solve]
        );
        return $this->bottom_anchor($processed);
    }

    private function intra_anchor($color, &$solve)
    {
        return "{$color[1]}{$color[2]}{$color[1]}";
    }

    // Constrained setters
    public function setHeading(string $v): self  { $this->heading  = $v; return $this; }
    public function setLeadhead(string $v): self { $this->leadhead = $v; return $this; }
    public function setTagname(string $v): self  { $this->tagname  = $v; return $this; }
    public function setLeadtail(string $v): self { $this->leadtail = $v; return $this; }
    public function setHead(string $v): self     { $this->head     = $v; return $this; }
    public function setTail(string $v): self     { $this->tail     = $v; return $this; }
    public function setTailhead(string $v): self { $this->tailhead = $v; return $this; }
    public function setTailtail(string $v): self { $this->tailtail = $v; return $this; }
    public function setTailing(string $v): self  { $this->tailing  = $v; return $this; }

    public function setText(string $v): self
    {
        $this->text = $v;
        $this->context = $v;
        return $this;
    }

    public function __get($label)
    {
        if ($label === 'text') {
            return $this->text;
        }
        if ($label === 'resolvedHref') {
            return $this->resolvedHref;
        }
        if (property_exists($this, $label)) {
            return $this->$label;
        }
        return null;
    }

    public function __set($label, $value)
    {
        if ($label === 'text' && is_string($value)) {
            $this->setText($value);
            return;
        }
        $allowed = [
            'heading','leadhead','tagname','leadtail','head',
            'tail','tailhead','tailtail','tailing','sender','anchor'
        ];
        if (in_array($label, $allowed, true) && !is_null($value)) {
            $this->$label = $value;
        }
    }

    public function toString(): string
    {
        $this->context = $this->text;

        $map = [
            '{heading}'  => $this->heading,
            '{leadhead}' => $this->leadhead,
            '{tagname}'  => $this->tagname,
            '{leadtail}' => $this->leadtail,
            '{head}'     => $this->head,
            '{context}'  => $this->context,
            '{tail}'     => $this->tail,
            '{tailhead}' => $this->tailhead,
            '{tailtail}' => $this->tailtail,
            '{tailing}'  => $this->tailing,
        ];
        return strtr(self::TEMPLATE, $map);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toJSON(): string
    {
        return json_encode([
            'heading'       => $this->heading,
            'leadhead'      => $this->leadhead,
            'tagname'       => $this->tagname,
            'leadtail'      => $this->leadtail,
            'head'          => $this->head,
            'text'          => $this->text,
            'context'       => $this->text,
            'tail'          => $this->tail,
            'tailhead'      => $this->tailhead,
            'tailtail'      => $this->tailtail,
            'tailing'       => $this->tailing,
            'anchor'        => $this->anchor,
            'resolvedHref'  => $this->resolvedHref,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function fromString(string $input): self
    {
        $obj = new self();
        if (strpos($input, '/a/') !== false) {
            $obj->edify($input);
        }
        return $obj;
    }

    public static function fromJSON(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return new self();
        }
        $obj = new self();
        if (isset($data['text']) && is_string($data['text'])) {
            $obj->text = $data['text'];
        }
        if (isset($data['resolvedHref']) && is_string($data['resolvedHref'])) {
            $obj->resolvedHref = $data['resolvedHref'];
            $obj->leadtail = ' href="' . htmlspecialchars($data['resolvedHref'], ENT_QUOTES, 'UTF-8') . '"';
        }
        foreach ($data as $key => $val) {
            if (in_array($key, ['text', 'resolvedHref'], true)) continue;
            if (property_exists($obj, $key) && is_string($val)) {
                $obj->$key = $val;
            }
        }
        $obj->context = $obj->text;
        return $obj;
    }
}

// ---------- Example ----------
// Global map of id => crawlable URL
$GLOBALS['hrefMap'] = [
    'ttest' => 'https://example.com/real-page',
];

$_REQUEST = ['/' => '/a/ttest'];
$a = new HtmlAnchorObject($_REQUEST['/']);
$a->text = 'Click me';

echo $a->toString() . PHP_EOL;
// <a href="https://example.com/real-page">Click me</a>

// Direct URL also works
$b = new HtmlAnchorObject();
$b->href('https://example.com/direct')->text = 'Direct';
echo $b . PHP_EOL;
