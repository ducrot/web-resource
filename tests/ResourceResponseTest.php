<?php

namespace TS\Web\Resource;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use DateTime;
use DateTimeImmutable;

class ResourceResponseTest extends TestCase
{

    private function createResource(array $options = []): Resource
    {
        return new Resource(array_merge([
            'content' => 'Hello World',
            'mimetype' => 'text/plain',
            'filename' => 'test.txt',
            'hash' => 'abc123',
            'lastmodified' => new DateTime('2024-01-15 12:00:00'),
        ], $options));
    }

    // ===========================================
    // Constructor Tests
    // ===========================================

    public function testConstructorWithDefaults()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $this->assertSame($resource, $response->getResource());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    public function testConstructorWithPrivateCache()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource, 200, [], false);

        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testConstructorWithCustomStatus()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource, 201);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testConstructorWithCustomHeaders()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource, 200, ['X-Custom' => 'value']);

        $this->assertSame('value', $response->headers->get('X-Custom'));
    }

    public function testConstructorWithContentDisposition()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource, 200, [], true, ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('test.txt', $response->headers->get('Content-Disposition'));
    }

    public function testConstructorWithAutoEtag()
    {
        $resource = $this->createResource(['hash' => 'myhash123']);
        $response = new ResourceResponse($resource, 200, [], true, null, true);

        $this->assertSame('"myhash123"', $response->getEtag());
    }

    public function testConstructorWithAutoLastModifiedEnabled()
    {
        $date = new DateTime('2024-01-15 12:00:00');
        $resource = $this->createResource(['lastmodified' => $date]);
        $response = new ResourceResponse($resource, 200, [], true, null, false, true);

        $this->assertEquals($date->getTimestamp(), $response->getLastModified()->getTimestamp());
    }

    public function testConstructorWithAutoLastModifiedDisabled()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $this->assertNull($response->getLastModified());
    }

    // ===========================================
    // setResource / getResource Tests
    // ===========================================

    public function testGetResource()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $this->assertSame($resource, $response->getResource());
    }

    public function testSetResourceWithAutoEtag()
    {
        $resource1 = $this->createResource(['hash' => 'hash1']);
        $resource2 = $this->createResource(['hash' => 'hash2']);

        $response = new ResourceResponse($resource1, 200, [], true, null, false, false);
        $this->assertNull($response->getEtag());

        $response->setResource($resource2, null, true, false);
        $this->assertSame('"hash2"', $response->getEtag());
    }

    public function testSetResourceWithAutoLastModified()
    {
        $date1 = new DateTime('2024-01-01');
        $date2 = new DateTime('2024-06-15');
        $resource1 = $this->createResource(['lastmodified' => $date1]);
        $resource2 = $this->createResource(['lastmodified' => $date2]);

        $response = new ResourceResponse($resource1, 200, [], true, null, false, false);
        $response->setResource($resource2, null, false, true);

        $this->assertEquals($date2->getTimestamp(), $response->getLastModified()->getTimestamp());
    }

    // ===========================================
    // setAutoLastModified Tests
    // ===========================================

    public function testSetAutoLastModifiedWithDateTime()
    {
        $date = new DateTime('2024-03-20 15:30:00');
        $resource = $this->createResource(['lastmodified' => $date]);
        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $result = $response->setAutoLastModified();

        $this->assertSame($response, $result); // method chaining
        $this->assertEquals($date->getTimestamp(), $response->getLastModified()->getTimestamp());
    }

    public function testSetAutoLastModifiedWithDateTimeImmutable()
    {
        $date = new DateTimeImmutable('2024-03-20 15:30:00');
        $resource = $this->createResource(['lastmodified' => $date]);
        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $response->setAutoLastModified();

        $this->assertEquals($date->getTimestamp(), $response->getLastModified()->getTimestamp());
    }

    public function testSetAutoLastModifiedWithNullFromMock()
    {
        $resource = $this->createMock(ResourceInterface::class);
        $resource->method('getLastModified')->willReturn(null);
        $resource->method('getHash')->willReturn('abc');
        $resource->method('getFilename')->willReturn('test.txt');
        $resource->method('getMimetype')->willReturn('text/plain');
        $resource->method('getLength')->willReturn(10);

        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $response->setAutoLastModified();

        $this->assertNull($response->getLastModified());
    }

    // ===========================================
    // setAutoEtag Tests
    // ===========================================

    public function testSetAutoEtag()
    {
        $resource = $this->createResource(['hash' => 'somehash']);
        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $result = $response->setAutoEtag();

        $this->assertSame($response, $result); // method chaining
        $this->assertSame('"somehash"', $response->getEtag());
    }

    public function testSetAutoEtagWithNullHashFromMock()
    {
        $resource = $this->createMock(ResourceInterface::class);
        $resource->method('getHash')->willReturn(null);
        $resource->method('getLastModified')->willReturn(new DateTime());
        $resource->method('getFilename')->willReturn('test.txt');
        $resource->method('getMimetype')->willReturn('text/plain');
        $resource->method('getLength')->willReturn(10);

        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $response->setAutoEtag();

        $this->assertNull($response->getEtag());
    }

    // ===========================================
    // setContentDisposition Tests
    // ===========================================

    public function testSetContentDispositionAttachment()
    {
        $resource = $this->createResource(['filename' => 'report.pdf']);
        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $result = $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        $this->assertSame($response, $result); // method chaining
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('report.pdf', $disposition);
    }

    public function testSetContentDispositionInline()
    {
        $resource = $this->createResource(['filename' => 'image.jpg']);
        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('inline', $disposition);
        $this->assertStringContainsString('image.jpg', $disposition);
    }

    public function testSetContentDispositionWithNonAsciiFilename()
    {
        $resource = $this->createResource(['filename' => 'tëst-fïlé.txt']);
        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        // Should have a fallback filename (non-ASCII chars are handled)
        $this->assertStringContainsString('filename=', $disposition);
    }

    public function testSetContentDispositionWithPercentInFilename()
    {
        $resource = $this->createResource(['filename' => '50%off.txt']);
        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        // Should have a fallback filename with _ replacing %
        $this->assertStringContainsString('50_off.txt', $disposition);
    }

    public function testSetContentDispositionWithSpaces()
    {
        $resource = $this->createResource(['filename' => 'my file.txt']);
        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('my file.txt', $disposition);
    }

    // ===========================================
    // prepare() Tests
    // ===========================================

    public function testPrepareSetsCotentLength()
    {
        $resource = $this->createResource(['content' => '12345']); // 5 bytes
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $response->prepare($request);

        $this->assertSame('5', $response->headers->get('Content-Length'));
    }

    public function testPrepareSetsCotentType()
    {
        $resource = $this->createResource(['mimetype' => 'application/pdf']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $response->prepare($request);

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testPrepareSetsFallbackContentType()
    {
        $resource = $this->createMock(ResourceInterface::class);
        $resource->method('getMimetype')->willReturn(null);
        $resource->method('getLastModified')->willReturn(new DateTime());
        $resource->method('getHash')->willReturn('abc');
        $resource->method('getFilename')->willReturn('test.bin');
        $resource->method('getLength')->willReturn(100);
        $resource->method('getStream')->willReturn(fopen('php://memory', 'r'));

        $response = new ResourceResponse($resource, 200, [], true, null, false, false);

        $request = Request::create('/test');
        $response->prepare($request);

        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function testPrepareSetsAcceptRangesForSafeMethod()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $request = Request::create('/test', 'GET');
        $response->prepare($request);

        $this->assertSame('bytes', $response->headers->get('Accept-Ranges'));
    }

    public function testPrepareSetsAcceptRangesNoneForUnsafeMethod()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $request = Request::create('/test', 'POST');
        $response->prepare($request);

        $this->assertSame('none', $response->headers->get('Accept-Ranges'));
    }

    public function testPrepareDoesNotOverrideExistingAcceptRanges()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);
        $response->headers->set('Accept-Ranges', 'custom');

        $request = Request::create('/test');
        $response->prepare($request);

        $this->assertSame('custom', $response->headers->get('Accept-Ranges'));
    }

    public function testPrepareDoesNotOverrideExistingContentType()
    {
        $resource = $this->createResource(['mimetype' => 'text/plain']);
        $response = new ResourceResponse($resource);
        $response->headers->set('Content-Type', 'text/html');

        $request = Request::create('/test');
        $response->prepare($request);

        $this->assertSame('text/html', $response->headers->get('Content-Type'));
    }

    public function testPrepareSetsProtocolVersion()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.1');
        $response->prepare($request);

        $this->assertSame('1.1', $response->getProtocolVersion());
    }

    public function testPrepareKeepsHttp10Protocol()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.0');
        $response->prepare($request);

        // Should not change to 1.1 when client is 1.0
        $this->assertSame('1.0', $response->getProtocolVersion());
    }

    public function testPrepareReturnsThis()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $result = $response->prepare($request);

        $this->assertSame($response, $result);
    }

    // ===========================================
    // Range Request Tests
    // ===========================================

    public function testRangeRequestSimple()
    {
        // Content: "Hello World" = 11 bytes (indices 0-10)
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=0-4'); // "Hello"
        $response->prepare($request);

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('bytes 0-4/11', $response->headers->get('Content-Range'));
        $this->assertSame('5', $response->headers->get('Content-Length'));
    }

    public function testRangeRequestOpenEnded()
    {
        // Content: "Hello World" = 11 bytes
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=6-'); // "World"
        $response->prepare($request);

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('bytes 6-10/11', $response->headers->get('Content-Range'));
        $this->assertSame('5', $response->headers->get('Content-Length'));
    }

    public function testRangeRequestSuffix()
    {
        // Content: "Hello World" = 11 bytes
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=-5'); // Last 5 bytes: "World"
        $response->prepare($request);

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('bytes 6-10/11', $response->headers->get('Content-Range'));
        $this->assertSame('5', $response->headers->get('Content-Length'));
    }

    public function testRangeRequestFullFile()
    {
        // Content: "Hello World" = 11 bytes
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=0-10'); // Full file
        $response->prepare($request);

        // Full file range should return 200, not 206
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('Content-Range'));
    }

    public function testRangeRequestOutOfBounds()
    {
        // Content: "Hello World" = 11 bytes (indices 0-10)
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=0-100'); // End beyond file size
        $response->prepare($request);

        $this->assertSame(416, $response->getStatusCode());
        $this->assertSame('bytes */11', $response->headers->get('Content-Range'));
    }

    public function testRangeRequestNegativeStart()
    {
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=-100'); // More than file size
        $response->prepare($request);

        // When suffix is larger than file, start becomes negative
        // This should result in 416
        $this->assertSame(416, $response->getStatusCode());
    }

    public function testRangeRequestStartGreaterThanEnd()
    {
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=5-2'); // Invalid: start > end
        $response->prepare($request);

        // Invalid range should be ignored, return full file
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRangeRequestWithValidIfRangeEtag()
    {
        $resource = $this->createResource(['content' => 'Hello World', 'hash' => 'abc123']);
        $response = new ResourceResponse($resource, 200, [], true, null, true, false);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=0-4');
        $request->headers->set('If-Range', '"abc123"');
        $response->prepare($request);

        $this->assertSame(206, $response->getStatusCode());
    }

    public function testRangeRequestWithInvalidIfRangeEtag()
    {
        $resource = $this->createResource(['content' => 'Hello World', 'hash' => 'abc123']);
        $response = new ResourceResponse($resource, 200, [], true, null, true, false);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=0-4');
        $request->headers->set('If-Range', '"different-etag"');
        $response->prepare($request);

        // Invalid If-Range should return full file
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('Content-Range'));
    }

    public function testRangeRequestWithValidIfRangeLastModified()
    {
        $date = new DateTime('2024-01-15 12:00:00');
        $resource = $this->createResource(['content' => 'Hello World', 'lastmodified' => $date]);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=0-4');
        $request->headers->set('If-Range', $date->format('D, d M Y H:i:s') . ' GMT');
        $response->prepare($request);

        $this->assertSame(206, $response->getStatusCode());
    }

    public function testRangeRequestWithInvalidIfRangeLastModified()
    {
        $date = new DateTime('2024-01-15 12:00:00');
        $resource = $this->createResource(['content' => 'Hello World', 'lastmodified' => $date]);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=0-4');
        $request->headers->set('If-Range', 'Wed, 01 Jan 2020 00:00:00 GMT');
        $response->prepare($request);

        // Invalid If-Range should return full file
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNoRangeHeaderReturns200()
    {
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $response->prepare($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('11', $response->headers->get('Content-Length'));
    }

    // ===========================================
    // sendContent Tests
    // ===========================================

    public function testSendContentFullFile()
    {
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $response->prepare($request);

        ob_start();
        $result = $response->sendContent();
        $output = ob_get_clean();

        $this->assertSame($response, $result);
        $this->assertSame('Hello World', $output);
    }

    public function testSendContentPartialRange()
    {
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=0-4');
        $response->prepare($request);

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertSame('Hello', $output);
    }

    public function testSendContentWithOffset()
    {
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=6-10');
        $response->prepare($request);

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertSame('World', $output);
    }

    public function testSendContentNonSuccessfulStatus()
    {
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource, 404);

        $request = Request::create('/test');
        $response->prepare($request);

        ob_start();
        $result = $response->sendContent();
        ob_get_clean();

        $this->assertSame($response, $result);
    }

    public function testSendContentZeroLengthRange()
    {
        $resource = $this->createResource(['content' => 'Hello World']);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=5-5');
        $response->prepare($request);

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertSame(' ', $output); // Single space at index 5
    }

    // ===========================================
    // setContent / getContent Tests
    // ===========================================

    public function testSetContentWithNullAllowed()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $result = $response->setContent(null);

        $this->assertSame($response, $result);
    }

    public function testSetContentWithValueThrowsException()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $this->expectException(Exception\LogicException::class);
        $this->expectExceptionMessage('The content cannot be set on a ResourceResponse instance.');

        $response->setContent('some content');
    }

    public function testGetContentReturnsFalse()
    {
        $resource = $this->createResource();
        $response = new ResourceResponse($resource);

        $this->assertFalse($response->getContent());
    }

    // ===========================================
    // Integration Tests with FileResource
    // ===========================================

    public function testWithFileResource()
    {
        $file = __DIR__ . '/Data/plaintext.txt';
        $resource = new FileResource($file);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $response->prepare($request);

        $this->assertSame('10', $response->headers->get('Content-Length'));
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertSame('plain text', $output);
    }

    public function testFileResourceRangeRequest()
    {
        $file = __DIR__ . '/Data/plaintext.txt';
        $resource = new FileResource($file);
        $response = new ResourceResponse($resource);

        $request = Request::create('/test');
        $request->headers->set('Range', 'bytes=0-4');
        $response->prepare($request);

        $this->assertSame(206, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertSame('plain', $output);
    }

}
