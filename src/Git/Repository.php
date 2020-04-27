<?php


namespace Decaf\StandardVersion\Git;

use Composer\Factory;
use Decaf\StandardVersion\Providers\None;
use Exception;


class Repository
{
    public string $provider;
    public string $host;
    public string $org;
    public string $name;
    public ?string $ticketUrl;
    public ?string $tagPrefix;

    public function __construct($extra)
    {
        $this->provider = None::class;

        $this->ticketUrl = $extra['ticket-url'] ?? null;
        $this->tagPrefix = $extra['tag-prefix'] ?? null;
    }

    /**
     * @param  string  $url
     *
     * @throws Exception
     */
    public function init(string $url): void
    {
        $pattern = '#(?<host>[a-z-\.]+)[/:](?<org>[^/]+)/(?<repo>[^\.]+).git$#i';

        if (preg_match($pattern, $url, $match)) {
            $this->host = $match['host'];
            $this->org  = $match['org'];
            $this->name = $match['repo'];
        } else {
            throw new Exception('unable to parse git url: ' . $url);
        }
    }

    /**
     * @param  string  $version1
     * @param  string  $version2
     *
     * @return string
     */
    public function getCompareUrl(string $version1, string $version2): string
    {
        $url = $this->provider::COMPARE_URL;

        $url = str_replace('#ORG#', $this->org, $url);
        $url = str_replace('#REPO#', $this->name, $url);
        $url = str_replace('#PREVIOUS#', $version1, $url);
        $url = str_replace('#NEXT#', $version2, $url);

        return $url;
    }

    /**
     * @param  string  $ref
     *
     * @return string
     */
    public function getCommitUrl(string $ref): string
    {
        $url = $this->provider::COMMIT_URL;

        $url = str_replace('#ORG#', $this->org, $url);
        $url = str_replace('#REPO#', $this->name, $url);
        $url = str_replace('#REF#', $ref, $url);

        return $url;
    }

    /**
     * @return string|null
     */
    public function getTicketUrl(): ?string
    {
        $url = $this->ticketUrl;

        if (!empty($url)) {
            $url = rtrim($url, '/') . '/';
        }

        return $url;
    }
}
