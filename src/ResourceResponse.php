<?php

namespace TS\Web\Resource;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TS\Web\Resource\Exception\LogicException;


/**
 * Extends Symfony's Response class to handle streaming resources with:
 * - HTTP range request support (206 Partial Content)
 * - Auto-generated ETags and Last-Modified headers
 * - Content-Disposition header handling
 * - Stream-based content delivery
 *
 * @author Timo Stamm <ts@timostamm.de>
 * @license AGPLv3.0 https://www.gnu.org/licenses/agpl-3.0.txt
 */
class ResourceResponse extends Response
{

    /** @var ResourceInterface */
	private $resource;

	/** @var int */
	private $offset;

    /** @var int */
	private $maxlen;


	public function __construct(ResourceInterface $resource, $status = 200, $headers = [], $public = true, $contentDisposition = null, $autoEtag = false, $autoLastModified = true)
	{
		
		parent::__construct(null, $status, $headers);
		
		$this->setResource($resource, $contentDisposition, $autoEtag, $autoLastModified);
		
		if ($public) {
			$this->setPublic();
		}
	}

	public function setResource(ResourceInterface $resource, $contentDisposition = null, $autoEtag = false, $autoLastModified = true): void
    {
		
		$this->resource = $resource;
		
		if ($autoEtag) {
			$this->setAutoEtag();
		}
		
		if ($autoLastModified) {
			$this->setAutoLastModified();
		}
		
		if ($contentDisposition) {
			$this->setContentDisposition($contentDisposition);
		}
	
	}


    /**
     * @return ResourceInterface
     */
    public function getResource(): ResourceInterface
    {
        return $this->resource;
    }


	public function setAutoLastModified(): static
    {
	    $in = $this->resource->getLastModified();
	    if ($in instanceof \DateTimeImmutable) {
	        $date = new \DateTime();
	        $date->setTimestamp($in->getTimestamp());
            $this->setLastModified($date);
        } else if ($in instanceof \DateTime) {
            $this->setLastModified($in);
        }
		return $this;
	}

	public function setAutoEtag(): static
    {
		$this->setEtag($this->resource->getHash());
		return $this;
	}

	public function setContentDisposition($disposition): static
    {
		$filename = $this->resource->getFilename();
		
		$filenameFallback = '';
		
		if (! preg_match('/^[\x20-\x7e]*$/', $filename) || false !== strpos($filename, '%')) {
			$encoding = mb_detect_encoding($filename, null, true);
			
			for ($i = 0; $i < mb_strlen($filename, $encoding); ++ $i) {
				$char = mb_substr($filename, $i, 1, $encoding);
				
				if ('%' === $char || ord($char) < 32 || ord($char) > 126) {
					$filenameFallback .= '_';
				} else {
					$filenameFallback .= $char;
				}
			}
		}
		
		$dispositionHeader = $this->headers->makeDisposition($disposition, $filename, $filenameFallback);
		$this->headers->set('Content-Disposition', $dispositionHeader);
		
		return $this;
	}

	/**
	 *
	 * {@inheritdoc}
	 */
	public function prepare(Request $request): static
    {
		$this->headers->set('Content-Length', $this->resource->getLength());
		
		if (! $this->headers->has('Accept-Ranges')) {
			// Only accept ranges on safe HTTP methods
			$this->headers->set('Accept-Ranges', $request->isMethodSafe( false ) ? 'bytes' : 'none');
		}
		
		if (! $this->headers->has('Content-Type')) {
			$this->headers->set('Content-Type', $this->resource->getMimetype() ?: 'application/octet-stream');
		}
		
		if ('HTTP/1.0' !== $request->server->get('SERVER_PROTOCOL')) {
			$this->setProtocolVersion('1.1');
		}
		
		$this->offset = 0;
		$this->maxlen = - 1;
		
		if ($request->headers->has('Range')) {
			// Process the range headers.
			if (! $request->headers->has('If-Range') || $this->hasValidIfRangeHeader($request->headers->get('If-Range'))) {
				$range = $request->headers->get('Range');
				$fileSize = $this->resource->getLength();
				
				list ($start, $end) = explode('-', substr($range, 6), 2) + array(
					0
				);
				
				$end = ('' === $end) ? $fileSize - 1 : (int) $end;
				
				if ('' === $start) {
					$start = $fileSize - $end;
					$end = $fileSize - 1;
				} else {
					$start = (int) $start;
				}
				
				if ($start <= $end) {
					if ($start < 0 || $end > $fileSize - 1) {
						$this->setStatusCode(416);
						$this->headers->set('Content-Range', sprintf('bytes */%s', $fileSize));
					} elseif ($start !== 0 || $end !== $fileSize - 1) {
						$this->maxlen = $end < $fileSize ? $end - $start + 1 : - 1;
						$this->offset = $start;
						
						$this->setStatusCode(206);
						$this->headers->set('Content-Range', sprintf('bytes %s-%s/%s', $start, $end, $fileSize));
						$this->headers->set('Content-Length', $end - $start + 1);
					}
				}
			}
		}
		
		return $this;
	}

	private function hasValidIfRangeHeader($header)
	{
		if ($this->getEtag() === $header) {
			return true;
		}
		
		if (null === $lastModified = $this->getLastModified()) {
			return false;
		}
		
		return $lastModified->format('D, d M Y H:i:s') . ' GMT' === $header;
	}

	/**
	 * Sends the resource.
	 *
	 * {@inheritdoc}
	 */
	public function sendContent(): static
    {
		if (! $this->isSuccessful()) {
			return parent::sendContent();
		}
		
		if (0 === $this->maxlen) {
			return $this;
		}
		
		$out = fopen('php://output', 'wb');
		$in = $this->resource->getStream();
		
		stream_copy_to_stream($in, $out, $this->maxlen, $this->offset);
		
		fclose($out);
		fclose($in);
		
		return $this;
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @throws \LogicException when the content is not null
	 */
	public function setContent($content): static
    {
		if (null !== $content) {
			throw new LogicException('The content cannot be set on a ResourceResponse instance.');
		}
        return $this;
	}

	/**
	 *
	 * {@inheritdoc}
	 */
	public function getContent(): false|string
    {
		return false;
	}

}

