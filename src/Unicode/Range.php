<?php


namespace Codepoints\Unicode;


/**
 * an arbitrary range of Unicode characters
 *
 * the range may have gaps
 */
class Range implements Iterator {

    /**
     * the database from which CP info is fetched
     */
    protected $db;

    /**
     * the set of codepoint instances
     */
    protected $set;

    /**
     * the provisional set of codepoints
     */
    protected $_set;

    /**
     * construct a new Unicode range
     *
     * The set may be an empty range and can be filled later.
     */
    public function __construct(Array $set/*=[]*/, $db) {
        $this->db = $db;
        $set = array_unique($set);
        $this->_set = $set; # cache set for later lazy loading
    }

    /**
     * prepare the set by fetching codepoints
     */
    protected function _prepare() {
        if ($this->set === Null) {
            $this->set = $this->fetchNames($this->_set);
        }
    }

    /**
     * get the current set of codepoints
     */
    public function get() {
        $this->_prepare();
        reset($this->set);
        return $this->set;
    }

    public function rewind() {
        $this->_prepare();
        reset($this->set);
    }

    public function current() {
        $this->_prepare();
        return current($this->set);
    }

    public function key() {
        $this->_prepare();
        return key($this->set);
    }

    public function next() {
        $this->_prepare();
        return next($this->set);
    }

    public function valid() {
        $this->_prepare();
        return key($this->set) !== NULL;
    }

    /**
     * get the IDs of the first and last valid codepoints
     * in the set
     */
    public function getBoundaries() {
        $this->_prepare();
        $indices = array_keys($this->set);
        if (! count($indices)) {
            return Null;
        }
        return [$indices[0], end($indices)];
    }

    /**
     * get the first codepoint ID from the set
     */
    public function getFirst() {
        $this->_prepare();
        $r = array_keys($this->set);
        if (! count($r)) {
            return Null;
        }
        return $r[0];
    }

    /**
     * get the last codepoint ID from the set
     */
    public function getLast() {
        $this->_prepare();
        $r = array_keys($this->set);
        return end($r);
    }

    /**
     * add a single codepoint to the set
     */
    public function add($cp) {
        $this->_prepare();
        $cp = intval($cp);
        if (! array_key_exists($cp, $this->set)) {
            $this->set += $this->fetchNames([$cp]);
        }
        return $this;
    }

    /**
     * add several codepoints to the set
     */
    public function addSet(Array $set) {
        $this->_prepare();
        $this->set += $this->fetchNames(array_diff(array_unique($set), array_keys($this->set)));
        return $this;
    }

    /**
     * add another range to the set
     */
    public function addRange(UnicodeRange $range) {
        $this->_prepare();
        $set = $range->get();
        $this->set = array_unique(array_merge($this->set, $set));
        return $this;
    }

    /**
     * slice the set of codepoints
     */
    public function slice($offset, $length=0) {
        $this->_set = array_slice($this->_set, $offset, $length);
    }

    /**
     * get the names of all characters in the set
     */
    protected function fetchNames($set) {
        $this->sanitizeSet($set);
        $names = [];
        if (count($set) > 0) {
            $query = $this->db->prepare("
              SELECT cp, na, na1, (SELECT codepoint_image.image
                                     FROM codepoint_image
                                    WHERE codepoint_image.cp = codepoints.cp) image
                FROM codepoints
               WHERE cp IN (" . join(',', $set) . ")");
            $query->execute();
            $r = $query->fetchAll(\PDO::FETCH_ASSOC);
            $query->closeCursor();
            if ($r !== False) {
                foreach ($r as $cp) {
                    if (! $cp['image']) {
                        $cp['image'] = '';
                    }
                    $names[intval($cp['cp'])] = Codepoint::getCP(intval($cp['cp']),
                        $this->db, ['name' => $cp['na']? $cp['na'] : ($cp['na1']? $cp['na1'].'*' : '<control>'),
                        'block' => $this, 'image' => 'data:image/png;base64,' . $cp['image']]);
                }
            }
        }
        return $names;
    }

    /**
     * remove non-integer values from a set
     */
    protected function sanitizeSet(&$set) {
        foreach ($set as $k => $v) {
            if (! is_int($v)) {
                if (is_string($v) && ctype_digit($v)) {
                    $set[$k] = intval($v);
                } else {
                    unset($set[$k]);
                }
            }
        }
        return $set;
    }

}


//EOF
