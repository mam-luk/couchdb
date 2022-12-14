<?php

namespace Mamluk\Couch\Database;

use Illuminate\Http\Client\Factory;
use Monolog\Logger;

class Client
{
    private string $url;

    /**
     * @var Factory
     */
    private Factory $http;

    /**
     * @var string Database name to connect to
     */
    private $database;

    /**
     * @var string CouchDB username
     */
    private string $user;

    /**
     * @var string CouchDB Password
     */
    private string $password;

    /**
     * @var string CouchDB host with http:// or https:// and no trailing slash!
     */
    private string $host;

    /**
     * @var int
     */
    private ?int $port;

    public Logger $log;


    /**
     * @param string $user
     * @param string $password
     * @param string $database
     * @param string $host
     * @param int $port
     * @param Factory $httpClient
     */
    public function __construct(string $user, string $password, string $database,
                                string $host, Logger $log, Factory $httpClient = new Factory(), ?int $port = 5984,
    )
    {
        $this->http = $httpClient;
        $this->database = $database;
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->log = $log;
        $this->setDatabase($database);
    }

    public function setDatabase(string $database): void
    {
        $this->database = $database;
        if ($this->port === null) {
            $this->url = $this->host . '/' . $database;
        } else {
            $this->url = $this->host . ':' . $this->port . '/' . $database;
        }
    }

    /**
     * Creates the database if it does not exist
     * @return bool
     */
    public function createDatabase(): bool
    {
        $existingDB = $this->http->withBasicAuth($this->user, $this->password)
            ->get($this->url);
        if (!$existingDB->ok()) {
            $dbCreated = $this->http->withBasicAuth($this->user, $this->password)
                ->put($this->url);

            return $dbCreated->status() < 300;
        }

        return true;
    }

    /**
     * @param string $id
     * @param string $document JSON encoded string
     * @return void
     */
    public function create(string $id, string $document): bool
    {
        $r = $this->http->withBasicAuth($this->user, $this->password)
            ->withBody($document, 'application/json')
            ->put($this->url . '/' . $id);

        return $r->status() < 300;
    }

    /**
     * @param string $id
     * @param string $document JSON encoded string
     * @return void
     */
    public function update(string $id, string $document, string $revision = null): bool
    {
        if ($revision === null) {
            $r = $this->http->withBasicAuth($this->user, $this->password)
                ->get($this->url . '/' . $id);
            if ($r->ok()) {
                $revision = $r->json()['_rev'];
            }
        }

        $r = $this->http->withBasicAuth($this->user, $this->password)
            ->withBody($document, 'application/json')
            ->put($this->url . '/' . $id . '?rev=' . $revision);

        if ($r->status() > 299) {
            $this->log->error('COUCHBASE ERROR: ' . $r->body());
        }

        return $r->status() < 300;
    }


    public function read(string $id): array
    {
        $r = $this->http->withBasicAuth($this->user, $this->password)
            ->get($this->url . '/' . $id);
        if ($r->ok()) {
            return $r->json();
        }

        return [];
    }

    /**
     * @param string $id
     * @param string|null $revision _rev from the document. If not provided, the document will be queried to get this value
     * @return bool
     */
    public function delete(string $id, string $revision = null): bool
    {
        if ($revision === null) {
            $r = $this->http->withBasicAuth($this->user, $this->password)
                ->get($this->url . '/' . $id);
            if ($r->ok()) {
                $revision = $r->json()['_rev'];
            }
        }

        $r = $this->http->withBasicAuth($this->user, $this->password)
            ->delete($this->url . '/' . $id . '?rev=' . $revision);

        return $r->ok();
    }
}