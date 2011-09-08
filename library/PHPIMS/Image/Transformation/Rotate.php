<?php
/**
 * PHPIMS
 *
 * Copyright (c) 2011 Christer Edvartsen <cogo@starzinger.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * * The above copyright notice and this permission notice shall be included in
 *   all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package PHPIMS
 * @subpackage ImageTransformation
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011, Christer Edvartsen
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/christeredvartsen/phpims
 */

namespace PHPIMS\Image\Transformation;

use PHPIMS\Client\ImageUrl;
use PHPIMS\Image\ImageInterface;

use Imagine\Imagick\Imagine;
use Imagine\Exception\Exception as ImagineException;
use Imagine\Image\Color;

/**
 * Rotate transformation
 *
 * @package PHPIMS
 * @subpackage ImageTransformation
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011, Christer Edvartsen
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/christeredvartsen/phpims
 * @see PHPIMS\Resource\Plugin\ManipulateImage
 */
class Rotate implements TransformationInterface {
    /**
     * Angle of the rotation
     *
     * @var int
     */
    private $angle;

    /**
     * Background color of the image
     *
     * @var string
     */
    private $bg = '000';

    /**
     * Class constructor
     *
     * @param int $angle Angle of the rotation
     * @param string $bg Background color
     */
    public function __construct($angle, $bg = null) {
        $this->angle = (int) $angle;

        if ($bg !== null) {
            $this->bg = $bg;
        }
    }

    /**
     * @see PHPIMS\Image\Transformation\TransformationInterface::applyToImage()
     */
    public function applyToImage(ImageInterface $image) {
        try {
            $imagine = new Imagine();
            $imagineImage = $imagine->load($image->getBlob());

            $imagineImage->rotate($this->angle, new Color($this->bg));

            $box = $imagineImage->getSize();

            $image->setBlob($imagineImage->get($image->getExtension()))
                  ->setWidth($box->getWidth())
                  ->setHeight($box->getHeight());
        } catch (ImagineException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @see PHPIMS\Image\Transformation\TransformationInterface::applyToImageUrl()
     */
    public function applyToImageUrl(ImageUrl $url) {
        $params = array(
            'angle=' . $this->angle,
            'bg=' . $this->bg,
        );

        $url->append('rotate:' . implode(',', $params));
    }
}