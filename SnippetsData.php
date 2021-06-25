<?php namespace ProcessWire;

/**
 * Snippets Data
 *
 * This is a wrapper class for WireData with some additions/modifications.
 *
 * @version 0.1.0
 * @copyright 2021 Teppo Koivula
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class SnippetsData extends WireData {

    /**
     * Constructor
     *
     * @param array Stored values
     */
    public function __construct(array $values = []) {
        $this->setArray($values);
    }

    /**
     * Retrieve the value for a previously set property
     *
     * @param string|object $key Name of property you want to retrieve
     * @return mixed|null Returns value of requested property, or null if the property was not found
     * @see WireData::set()
     */
    public function get($key) {
        if (strpos($key, '.')) {
			$prop = substr($key, 0, strpos($key, '.'));
			if ($prop == 'page') {
				return $this->data($prop)->get(substr($key, strpos($key, '.') + 1));
			}
            return $this->getDot($key);
        }
        return parent::get($key);
    }

}
