<?php

namespace CommonsBooking\Wordpress\CustomPostType;

/**
 * Shared meta field keys that are used in more than one place (e.g. both the
 * metabox registration and the API routes that read the stored value).
 *
 * The backing value is the field suffix. The full meta key, as registered with
 * CMB2 and stored in the database, is produced by {@see MetaField::getFieldId()},
 * which prepends the global COMMONSBOOKING_METABOX_PREFIX so the prefix stays
 * defined in a single location.
 */
enum MetaField: string {

	/**
	 * Item flag: when set to `'on'` the item is excluded from all API shares.
	 */
	case ItemApiExclude = 'api_exclude';

	/**
	 * Full meta key including the global metabox prefix.
	 *
	 * @return string
	 */
	public function getFieldId(): string {
		return COMMONSBOOKING_METABOX_PREFIX . $this->value;
	}
}
