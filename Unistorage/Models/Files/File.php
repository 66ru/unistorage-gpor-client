<?php namespace Unistorage\Models\Files;

use Unistorage\Models\Component;

/**
 * @property-read string $resourceUri
 */
abstract class File extends Component
{
    /**
     * @var string
     */
    protected $resourceUri;

    /**
     * @param string $resourceUri
     * @param array $config
     */
    public function __construct($resourceUri, $config = array())
    {
        $this->resourceUri = $resourceUri;

        foreach ($config as $property => $value) {
            $this->$property = $value;
        }
    }

    /**
     * @return string
     */
    public function getResourceUri()
    {
        return $this->resourceUri;
    }

    /**
     * @return integer return TTL or 0 if permanent
     */
    abstract public function getTtl();
}
