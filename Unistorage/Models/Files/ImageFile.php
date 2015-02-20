<?php namespace Unistorage\Models\Files;

use Unistorage\Unistorage;

/**
 * @property-read int $width
 * @property-read int $height
 */
class ImageFile extends RegularFile
{
    /**
     * @var int
     */
    protected $width;

    /**
     * @var int
     */
    protected $height;

    const MODE_KEEP_RATIO = 'keep';
    const MODE_CROP_RESIZE = 'crop';
    const MODE_EXACT = 'resize';

    const CORNER_LEFT_TOP = 'nw';
    const CORNER_LEFT_BOTTOM = 'sw';
    const CORNER_RIGHT_TOP = 'ne';
    const CORNER_RIGHT_BOTTOM = 'se';

    public function getHeight()
    {
        return $this->height;
    }

    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @param int $x1
     * @param int $y1
     * @param int $width
     * @param int $height
     * @param bool $lowPriority
     * @param Unistorage $unistorage
     * @return File
     */
    public function crop($x1, $y1, $width, $height, $lowPriority = false, $unistorage)
    {
        $actionParams = array(
            'x' => $x1,
            'y' => $y1,
            'w' => $width,
            'h' => $height
        );
        return $unistorage->applyAction($this, RegularFile::ACTION_CROP, $actionParams, $lowPriority);
    }

    /**
     * @param string $mode
     * @param int $width
     * @param int $height
     * @param bool $lowPriority
     * @param Unistorage $unistorage
     *
     * @return File
     */
    public function resize($mode, $width, $height, $lowPriority = false, $unistorage)
    {
        $actionParams = array(
            'mode' => $mode,
        );
        if (!empty($width)) {
            $actionParams['w'] = $width;
        }
        if (!empty($height)) {
            $actionParams['h'] = $height;
        }
        return $unistorage->applyAction($this, RegularFile::ACTION_RESIZE, $actionParams, $lowPriority);
    }

    /**
     * @param string $format
     * @param bool $lowPriority
     * @param Unistorage $unistorage
     *
     * @return File
     */
    public function convert($format, $lowPriority = false, $unistorage)
    {
        return $unistorage->applyAction(
            $this,
            RegularFile::ACTION_CONVERT,
            array(
                'to' => $format,
            ),
            $lowPriority
        );
    }

    /**
     * @param bool $lowPriority
     * @param Unistorage $unistorage
     *
     * @return File
     */
    public function grayscale($lowPriority = false, $unistorage)
    {
        return $unistorage->applyAction($this, RegularFile::ACTION_GRAYSCALE, array(), $lowPriority);
    }

    /**
     * @param bool $lowPriority
     * @param int $angle 90, 180, 270. CCW
     * @param Unistorage $unistorage
     *
     * @return File
     */
    public function rotate($angle, $lowPriority = false, $unistorage)
    {
        return $unistorage->applyAction(
            $this,
            RegularFile::ACTION_ROTATE,
            array(
                'angle' => $angle,
            ),
            $lowPriority
        );
    }

    /**
     * $wmWidth, $wmHeight, $horizontalPadding, $verticalPadding may have following format:
     * <ul>
     * <li> (\d+)px - number calculates in pixels
     * <li> (\d+) - number calculates in percents
     * </ul>
     *
     * @param ImageFile $watermark
     * @param string $wmWidth watermark width
     * @param string $wmHeight watermark height
     * @param string $horizontalPadding padding of watermark
     * @param string $verticalPadding padding of watermark
     * @param string $corner one of ImageFile::CORNER_*
     * @param bool $lowPriority
     * @param Unistorage $unistorage
     *
     * @return File
     */
    public function watermark(
        $watermark,
        $wmWidth,
        $wmHeight,
        $horizontalPadding,
        $verticalPadding,
        $corner,
        $lowPriority = false,
        $unistorage
    ) {
        return $unistorage->applyAction(
            $this,
            RegularFile::ACTION_WATERMARK,
            array(
                'watermark' => $watermark->resourceUri,
                'w'         => $wmWidth,
                'h'         => $wmHeight,
                'w_pad'     => $horizontalPadding,
                'h_pad'     => $verticalPadding,
                'corner'    => $corner,
            ),
            $lowPriority
        );
    }
}
