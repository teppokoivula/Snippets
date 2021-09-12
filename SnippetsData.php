<?php namespace ProcessWire;

/**
 * Snippets Data
 *
 * This is a wrapper class for WireData with some additions/modifications.
 *
 * @version 0.2.0
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
			if ($prop === 'page') {
				// actual property name (e.g. field, field.subfield, or field.0.field)
				$item_prop = substr($key, strpos($key, '.') + 1);
				return $this->getItemProp($this->data($prop), $item_prop);
			}
            return $this->getDot($key);
        }
        return parent::get($key);
    }

	/**
	 * Get item prop
	 *
	 * Supported prop name types:
	 *
	 * - null (just for convenience)
	 * - field name (field_name)
	 * - field and subfield names (field_name.subfield_name)
	 * - integers (for item index, e.g. 0 for the first item)
	 *
	 * ... and any combination of the above, such as field_name.subfield_name.0.title.
	 *
	 * @param Wire $item
	 * @param string|null $prop
	 * @return mixed
	 */
	protected function getItemProp(Wire $item, ?string $prop) {

		// split property name by dots for easier processing
		$props = $prop === null ? null : array_filter(explode('.', $prop), function($value) {
			return is_numeric($value) || !empty($value);
		});

		// bail out early if property array is empty
		if (empty($props)) return $item;

		// check if property name contains an index number
		$item_index = null;
		$sub_prop = null;
		foreach ($props as $props_key => $props_value) {
			if (is_numeric($props_value)) {
				$item_index = (int) $props_value;
				$sub_prop = implode('.', array_slice($props, $props_key + 1));
				$prop = $props_key === 0 ? null : implode('.', array_slice($props, 0, $props_key));
				break;
			}
		}

		// check if we can and should fetch data recursively
		if ($item_index !== null) {
			$sub_item = null;
			if ($prop === null && $item instanceof WireArray) {
				$sub_item = $item->eq($item_index);
			} else if ($prop !== null) {
				$sub_items = $this->getItemDotProp($item, $prop);
				if ($sub_items instanceof WireArray) {
					$sub_item = $sub_items->eq($item_index);
				}
			}
			if ($sub_item instanceof Wire) {
				return $this->getItemProp($sub_item, $sub_prop);
			}
		}

		// get data from item
		$data = $this->getItemDotProp($item, $prop);
		if (is_array($data)) {
			$data = empty($data) ? null : json_encode($data);
		}
		return $data;
	}

	/**
	 * Get dot prop from item
	 *
	 * @param Wire $item
	 * @param string|null $prop
	 * @return mixed
	 */
	protected function getItemDotProp(Wire $item, ?string $prop) {
		if ($prop === null) {
			return null;
		}
		if (strpos($prop, '.') === false) {
			return $item->get($prop);
		}
		if ($item instanceof WireData) {
			return $item->getDot($prop);
		}
		return self::_getDot($prop, $item);
	}

}
