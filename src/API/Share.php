<?php


namespace CommonsBooking\API;

/**
 * Defines an remote api consumer, where data is pushed to.
 * GBFS feed updates can be send via push actions to remote api consumers.
 *
 * @see user documentation for details regarding configuration https://commonsbooking.org/documentation/api/commonsbooking-api
 * @see the openTripPlanner docs for 'push updates' https://docs.opentripplanner.org/en/v2.2.0/UpdaterConfig/#configuring-real-time-updaters
 */
class Share {

	/** @var string */
	private $name;

	/** @var bool */
	private $enabled;

	/** @var string */
	private $pushUrl;

	/** @var string */
	private $key;

	/** @var string */
	private $owner;

	/**
	 * Shares constructor.
	 *
	 * @param string $name
	 * @param string $enabled
	 * @param string $pushUrl
	 * @param string $key
	 * @param string $owner
	 */
	public function __construct( string $name, string $enabled, string $pushUrl, string $key, string $owner ) {
		$this->name    = $name;
		$this->enabled = $enabled === 'on';
		$this->pushUrl = $pushUrl;
		$this->key     = $key;
		$this->owner   = $owner;
	}

	/**
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return mixed
	 */
	public function isEnabled() {
		return $this->enabled;
	}

	/**
	 * @return mixed
	 */
	public function getPushUrl() {
		return $this->pushUrl;
	}

	/**
	 * @return mixed
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * @return mixed
	 */
	public function getOwner() {
		return $this->owner;
	}
}
