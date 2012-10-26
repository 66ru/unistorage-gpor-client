<?php namespace Unistorage;

/**
 * @property-read string $resourceUri
 */
class Template extends \CComponent
{
	/**
	 * @var string
	 */
	private $resourceUri;

	function __construct($resourceUri)
	{
		$this->resourceUri = $resourceUri;
	}

	public function getResourceUri()
	{
		return $this->resourceUri;
	}
}
