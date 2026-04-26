<?php

declare(strict_types=1);

namespace Zero\Lib\Http;

use RuntimeException;
use SimpleXMLElement;
use SoapClient;
use SoapFault;
use SoapHeader;
use SoapVar;

class SoapRequest
{
    private ?string $wsdl;
    private array $options;
    /** @var array<int, SoapHeader> */
    private array $headers = [];
    /** @var array<int, array{name:string, namespace:string, data:mixed, mustUnderstand:bool, actor:?string}> */
    private array $headerSpecs = [];
    /** @var array<int, mixed> */
    private array $outputHeaders = [];
    private bool $trace = false;
    private bool $throwOnFault = false;
    /** @var class-string|null */
    private ?string $clientClass = null;

    public function __construct(?string $wsdl = null)
    {
        $this->wsdl = $wsdl;
        $this->options = [
            'trace' => 0,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 30,
        ];
    }

    public function wsdl(string $url): self
    {
        $this->wsdl = $url;
        return $this;
    }

    public function endpoint(string $url): self
    {
        $this->options['location'] = $url;
        if (! isset($this->options['uri']) && $this->wsdl === null) {
            $parts = parse_url($url);
            $this->options['uri'] = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost') . '/';
        }
        return $this;
    }

    public function uri(string $uri): self
    {
        $this->options['uri'] = $uri;
        return $this;
    }

    public function noWsdl(): self
    {
        $this->wsdl = null;
        return $this;
    }

    public function action(string $soapAction): self
    {
        $this->options['soap_action'] = $soapAction;
        return $this;
    }

    public function version(int $version): self
    {
        $this->options['soap_version'] = $version;
        return $this;
    }

    public function style(int $style): self
    {
        $this->options['style'] = $style;
        return $this;
    }

    public function encoding(string $encoding): self
    {
        $this->options['encoding'] = $encoding;
        return $this;
    }

    public function use(int $use): self
    {
        $this->options['use'] = $use;
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->options['connection_timeout'] = max(1, $seconds);
        return $this;
    }

    public function withWsdlCache(int $mode): self
    {
        $this->options['cache_wsdl'] = $mode;
        return $this;
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $this->options['authentication'] = SOAP_AUTHENTICATION_BASIC;
        $this->options['login'] = $username;
        $this->options['password'] = $password;
        return $this;
    }

    public function withDigestAuth(string $username, string $password): self
    {
        $this->options['authentication'] = SOAP_AUTHENTICATION_DIGEST;
        $this->options['login'] = $username;
        $this->options['password'] = $password;
        return $this;
    }

    public function withClientCertificate(string $path, ?string $passphrase = null): self
    {
        $this->options['local_cert'] = $path;
        if ($passphrase !== null) {
            $this->options['passphrase'] = $passphrase;
        }
        return $this;
    }

    public function withProxy(string $host, int $port, ?string $login = null, ?string $password = null): self
    {
        $this->options['proxy_host'] = $host;
        $this->options['proxy_port'] = $port;
        if ($login !== null) {
            $this->options['proxy_login'] = $login;
        }
        if ($password !== null) {
            $this->options['proxy_password'] = $password;
        }
        return $this;
    }

    public function withUserAgent(string $userAgent): self
    {
        $this->options['user_agent'] = $userAgent;
        return $this;
    }

    public function compression(int $flags): self
    {
        $this->options['compression'] = $flags;
        return $this;
    }

    public function keepAlive(bool $keepAlive = true): self
    {
        $this->options['keep_alive'] = $keepAlive;
        return $this;
    }

    public function withClassMap(array $classMap): self
    {
        $this->options['classmap'] = array_replace($this->options['classmap'] ?? [], $classMap);
        return $this;
    }

    public function withTypeMap(array $typeMap): self
    {
        $this->options['typemap'] = array_merge($this->options['typemap'] ?? [], $typeMap);
        return $this;
    }

    public function withFeatures(int $features): self
    {
        $this->options['features'] = $features;
        return $this;
    }

    public function withStreamContext($context): self
    {
        $this->options['stream_context'] = $context;
        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = array_replace($this->options, $options);
        return $this;
    }

    public function withSoapHeader(string $name, string $namespace, mixed $data = null, bool $mustUnderstand = false, ?string $actor = null): self
    {
        $this->headerSpecs[] = compact('name', 'namespace', 'data', 'mustUnderstand', 'actor');
        $this->headers[] = $actor !== null && $actor !== ''
            ? new SoapHeader($namespace, $name, $data, $mustUnderstand, $actor)
            : new SoapHeader($namespace, $name, $data, $mustUnderstand);
        return $this;
    }

    public function trace(bool $trace = true): self
    {
        $this->trace = $trace;
        $this->options['trace'] = $trace ? 1 : 0;
        return $this;
    }

    public function throw(bool $throw = true): self
    {
        $this->throwOnFault = $throw;
        return $this;
    }

    public function withClient(string $class): self
    {
        if (! is_subclass_of($class, SoapClient::class) && $class !== SoapClient::class) {
            throw new RuntimeException("withClient() expects a SoapClient subclass, got {$class}");
        }
        $this->clientClass = $class;
        return $this;
    }

    /**
     * Execute a SOAP method.
     */
    public function call(string $method, array $arguments = []): SoapResponse
    {
        if (extension_loaded('soap')) {
            return $this->callViaExtSoap($method, $arguments);
        }
        return $this->callViaCurl($method, $arguments);
    }

    /**
     * Magic accessor: $client->GetRates([...]).
     */
    public function __call(string $method, array $arguments): SoapResponse
    {
        $params = $arguments[0] ?? [];
        return $this->call($method, is_array($params) ? $params : [$params]);
    }

    /**
     * Build the underlying SoapClient (when ext-soap is available) for advanced use.
     */
    public function client(): SoapClient
    {
        if (! extension_loaded('soap')) {
            throw new RuntimeException('ext-soap is not loaded; cannot build a SoapClient instance.');
        }
        return $this->buildClient();
    }

    /**
     * Return the WSDL functions exposed by the service (requires ext-soap and a WSDL).
     *
     * @return array<int, string>
     */
    public function functions(): array
    {
        $client = $this->client();
        $functions = $client->__getFunctions();
        return is_array($functions) ? $functions : [];
    }

    /**
     * Return the WSDL-described complex types (requires ext-soap and a WSDL).
     *
     * @return array<int, string>
     */
    public function types(): array
    {
        $client = $this->client();
        $types = $client->__getTypes();
        return is_array($types) ? $types : [];
    }

    private function buildClient(): SoapClient
    {
        if ($this->wsdl === null && ! isset($this->options['uri'])) {
            throw new RuntimeException('Non-WSDL SOAP requests need both endpoint() and uri(); call ->endpoint($url)->uri($namespace) before sending.');
        }

        $class = $this->clientClass ?? SoapClient::class;
        try {
            return new $class($this->wsdl, $this->options);
        } catch (SoapFault $fault) {
            throw $fault;
        }
    }

    private function callViaExtSoap(string $method, array $arguments): SoapResponse
    {
        $client = $this->buildClient();

        $this->outputHeaders = [];
        $fault = null;
        $result = null;

        try {
            $result = $client->__soapCall(
                $method,
                array_values($arguments),
                null,
                $this->headers,
                $this->outputHeaders
            );
        } catch (SoapFault $caught) {
            $fault = $caught;
            if ($this->throwOnFault) {
                throw $caught;
            }
        }

        $lastRequest = $this->trace ? ($client->__getLastRequest() ?? null) : null;
        $lastRequestHeaders = $this->trace ? ($client->__getLastRequestHeaders() ?? null) : null;
        $lastResponse = $this->trace ? ($client->__getLastResponse() ?? null) : null;
        $lastResponseHeaders = $this->trace ? ($client->__getLastResponseHeaders() ?? null) : null;

        return new SoapResponse(
            $result,
            $this->outputHeaders ?? [],
            $fault,
            $lastRequest,
            $lastRequestHeaders,
            $lastResponse,
            $lastResponseHeaders,
            null
        );
    }

    /**
     * Manual fallback when ext-soap is not loaded. Caller is responsible for argument shape.
     */
    private function callViaCurl(string $method, array $arguments): SoapResponse
    {
        $endpoint = $this->options['location'] ?? $this->wsdl;
        if (! is_string($endpoint) || $endpoint === '') {
            throw new RuntimeException('SOAP fallback requires endpoint() to be set when ext-soap is not loaded.');
        }
        $namespace = $this->options['uri'] ?? 'urn:zero-soap';
        $version = $this->options['soap_version'] ?? SOAP_1_1;

        $envelope = $this->buildEnvelope($method, $arguments, $namespace, $version);

        $request = new PendingRequest();
        $request = $request
            ->contentType($version === SOAP_1_2 ? 'application/soap+xml; charset=utf-8' : 'text/xml; charset=utf-8')
            ->bodyFormat('body');

        if (isset($this->options['soap_action']) && $version === SOAP_1_1) {
            $request->withHeader('SOAPAction', '"' . $this->options['soap_action'] . '"');
        }
        if (isset($this->options['login']) && isset($this->options['password'])) {
            $request->withBasicAuth((string) $this->options['login'], (string) $this->options['password']);
        }
        if (isset($this->options['user_agent'])) {
            $request->withUserAgent((string) $this->options['user_agent']);
        }
        if (isset($this->options['connection_timeout'])) {
            $request->timeout((int) $this->options['connection_timeout']);
        }

        $response = $request->send('POST', $endpoint, $envelope);

        $body = $response->body();
        $fault = null;
        $result = null;
        $outputHeaders = [];

        if ($body !== '') {
            try {
                $xml = new SimpleXMLElement($body);
                $namespaces = $xml->getNamespaces(true);
                $envelopeNs = $namespaces['SOAP-ENV'] ?? $namespaces['soap'] ?? $namespaces['env'] ?? 'http://schemas.xmlsoap.org/soap/envelope/';
                $body_node = $xml->children($envelopeNs)->Body;
                if ($body_node !== null) {
                    $faultNode = $body_node->children($envelopeNs)->Fault;
                    if ($faultNode !== null && $faultNode->count() > 0) {
                        $code = (string) ($faultNode->faultcode ?? $faultNode->Code->Value ?? 'SOAP-ENV:Server');
                        $string = (string) ($faultNode->faultstring ?? $faultNode->Reason->Text ?? 'SOAP fault');
                        $fault = new SoapFault($code, $string);
                    } else {
                        $result = self::xmlToArray($body_node);
                    }
                }
                $headersNode = $xml->children($envelopeNs)->Header ?? null;
                if ($headersNode !== null) {
                    $outputHeaders = self::xmlToArray($headersNode);
                }
            } catch (\Throwable $e) {
                $fault = new SoapFault('Client', 'Failed to parse SOAP response: ' . $e->getMessage());
            }
        } elseif ($response->error() !== null) {
            $fault = new SoapFault('HTTP', $response->error());
        } elseif ($response->failed()) {
            $fault = new SoapFault('HTTP', 'HTTP ' . $response->status());
        }

        if ($this->throwOnFault && $fault !== null) {
            throw $fault;
        }

        $traceLastRequest = $this->trace ? $envelope : null;
        $traceLastResponse = $this->trace ? $body : null;

        return new SoapResponse(
            $result,
            $outputHeaders,
            $fault,
            $traceLastRequest,
            null,
            $traceLastResponse,
            null,
            $response->status()
        );
    }

    private function buildEnvelope(string $method, array $arguments, string $namespace, int $version): string
    {
        $envelopeNs = $version === SOAP_1_2
            ? 'http://www.w3.org/2003/05/soap-envelope'
            : 'http://schemas.xmlsoap.org/soap/envelope/';

        $headerXml = '';
        if ($this->headerSpecs !== []) {
            $headerXml = '<SOAP-ENV:Header>';
            foreach ($this->headerSpecs as $spec) {
                $headerXml .= sprintf(
                    '<ns:%s xmlns:ns="%s"%s>%s</ns:%s>',
                    self::xmlName($spec['name']),
                    htmlspecialchars($spec['namespace'], ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                    $spec['mustUnderstand'] ? ' SOAP-ENV:mustUnderstand="1"' : '',
                    self::valueToXml($spec['data']),
                    self::xmlName($spec['name'])
                );
            }
            $headerXml .= '</SOAP-ENV:Header>';
        }

        $bodyArgs = '';
        foreach ($arguments as $key => $value) {
            $tag = is_int($key) ? 'arg' . $key : self::xmlName((string) $key);
            $bodyArgs .= sprintf('<%s>%s</%s>', $tag, self::valueToXml($value), $tag);
        }

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="%s" xmlns:ns1="%s">%s<SOAP-ENV:Body><ns1:%s>%s</ns1:%s></SOAP-ENV:Body></SOAP-ENV:Envelope>',
            $envelopeNs,
            htmlspecialchars($namespace, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            $headerXml,
            self::xmlName($method),
            $bodyArgs,
            self::xmlName($method)
        );
    }

    private static function valueToXml(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof SoapVar) {
            return is_string($value->enc_value) ? $value->enc_value : (string) var_export($value->enc_value, true);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            $xml = '';
            foreach ((array) $value as $k => $v) {
                $tag = is_int($k) ? 'item' : self::xmlName((string) $k);
                $xml .= sprintf('<%s>%s</%s>', $tag, self::valueToXml($v), $tag);
            }
            return $xml;
        }
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function xmlName(string $name): string
    {
        $cleaned = preg_replace('/[^A-Za-z0-9_:.-]/', '', $name) ?? '';
        if ($cleaned === '' || ! preg_match('/^[A-Za-z_]/', $cleaned)) {
            return 'el_' . ltrim($cleaned, '0123456789');
        }
        return $cleaned;
    }

    private static function xmlToArray(SimpleXMLElement $node): mixed
    {
        $array = [];
        foreach ($node->getNamespaces(true) + ['' => null] as $prefix => $ns) {
            foreach (($ns === null ? $node->children() : $node->children($ns)) as $child) {
                $name = $child->getName();
                $value = $child->count() > 0 ? self::xmlToArray($child) : (string) $child;
                if (isset($array[$name])) {
                    if (! is_array($array[$name]) || ! array_is_list($array[$name])) {
                        $array[$name] = [$array[$name]];
                    }
                    $array[$name][] = $value;
                } else {
                    $array[$name] = $value;
                }
            }
        }
        return $array === [] ? (string) $node : $array;
    }
}
