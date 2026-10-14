<?php
namespace Imefisto\PsrSwoole;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request as SwooleRequest;

class ServerRequest extends Request implements ServerRequestInterface
{
    private array $attributes = [];
    private array $cookies;
    private array $files;
    private null|array|object $parsedBody = null;
    private bool $parsedBodyIsSet = false;
    private ?array $decodedJsonBody = null;
    private bool $jsonBodyDecoded = false;
    private array $query;

    public function __construct(
        SwooleRequest $swooleRequest,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
        private readonly UploadedFileFactoryInterface $uploadedFileFactory
    ) {
        parent::__construct($swooleRequest, $uriFactory, $streamFactory);
    }

    public function getServerParams(): array
    {
        return array_merge($_SERVER, array_change_key_case($this->swooleRequest->server ?? [], CASE_UPPER));
    }

    public function getCookieParams(): array
    {
        return $this->cookies ?? ($this->swooleRequest->cookie ?? []);
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookies = $cookies;
        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->query ?? ($this->swooleRequest->get ?? []);
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    public function getUploadedFiles(): array
    {
        if (isset($this->files)) {
            return $this->files;
        }

        $files = [];

        foreach ($this->swooleRequest->files ?? [] as $name => $fileData) {
            $files[$name] = $this->uploadedFileFactory->createUploadedFile(
                $this->streamFactory->createStreamFromFile($fileData['tmp_name']),
                $fileData['size'],
                $fileData['error'],
                $fileData['name'],
                $fileData['type']
            );
        }

        return $this->files = $files;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->files = $uploadedFiles;
        return $new;
    }

    public function getParsedBody()
    {
        if ($this->parsedBodyIsSet) {
            return $this->parsedBody;
        }
        
        if (!empty($this->swooleRequest->post)) {
            return $this->swooleRequest->post;
        }

        if ($this->isJsonRequest()) {
            if (!$this->jsonBodyDecoded) {
                $this->decodedJsonBody = $this->decodeJsonBody();
                $this->jsonBodyDecoded = true;
            }
            return $this->decodedJsonBody;
        }

        return null;
    }

    private function isJsonRequest(): bool
    {
        // application/json and structured suffixes like application/vnd.api+json
        return preg_match(
            '#^\s*application/([a-z0-9.!\#$&^_-]+\+)?json\s*(;|$)#i',
            $this->getHeaderLine('content-type')
        ) === 1;
    }

    /**
     * Swoole only fills `post` for form content types, so JSON is decoded
     * from the raw body. Only arrays and objects are valid parsed bodies;
     * empty, invalid or scalar JSON means there is no parsed body.
     */
    private function decodeJsonBody(): ?array
    {
        $rawBody = (string) $this->swooleRequest->rawContent();
        if ($rawBody === '') {
            return null;
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        if (!\is_object($data) && !\is_array($data) && !\is_null($data)) {
            throw new \InvalidArgumentException('Unsupported argument type');
        }

        $new = clone $this;
        $new->parsedBody = $data;
        $new->parsedBodyIsSet = true;
        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null)
    {
        return array_key_exists($name, $this->attributes)
            ? $this->attributes[$name]
            : $default
            ;
    }

    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
