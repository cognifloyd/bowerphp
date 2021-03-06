<?php

namespace Bowerphp\Repository;

use Guzzle\Http\ClientInterface;
use Guzzle\Http\Exception\RequestException;
use RuntimeException;

/**
 * GithubRepository
 *
 */
class GithubRepository implements RepositoryInterface
{
    protected $url, $tag = array(), $httpClient;

    /**
     * @param  string           $url
     * @param  boolean          $raw
     * @return GithubRepository
     */
    public function setUrl($url, $raw = true)
    {
        $this->url = preg_replace('/\.git$/', '', str_replace('git://', 'https://' . ($raw ? 'raw.' : ''), $url));

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param  ClientInterface  $httpClient
     * @return GithubRepository
     */
    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getBower($version = 'master')
    {
        $depBowerJsonURL = $this->url . '/' . $version . '/bower.json';
        try {
            $request = $this->httpClient->get($depBowerJsonURL);
            $response = $request->send();
            // we need this in case of redirect (e.g. 'less/less' becomes 'less/less.js')
            $this->setUrl($response->getEffectiveUrl());
        } catch (RequestException $e) {
            throw new RuntimeException(sprintf('Cannot open package git URL %s (%s).', $depBowerJsonURL, $e->getMessage()), 5);
        }

        return $response->getBody(true);
    }

    /**
     * {@inheritDoc}
     */
    public function findPackage($version = '*')
    {
        list($repoUser, $repoName) = explode('/', $this->clearGitURL($this->url));
        try {
            $githubTagsURL = sprintf('https://api.github.com/repos/%s/%s/tags', $repoUser, $repoName);
            $request = $this->httpClient->get($githubTagsURL);
            $response = $request->send();
            $tags = json_decode($response->getBody(true), true);
        } catch (RequestException $e) {
            throw new RuntimeException(sprintf('Cannot open repo %s/%s (%s).', $repoUser, $repoName, $e->getMessage()));
        }
        $version = $this->fixVersion($version);

        $tagsString = '';
        foreach ($tags as $tag) {
            if (fnmatch($version, $tag['name'])) {
                $this->setTag($tag);

                return $tag['name'];
            }
            $tagsString .= $tag['name'] . ', ';
        }
        $tagsString = substr($tagsString, 0, -2);

        throw new RuntimeException(sprintf('Version %s not found. Available versions: %s', $version, $tagsString));
    }

    /**
     * {@inheritDoc}
     */
    public function getRelease($type = 'zip')
    {
        $tag = $this->getTag();
        $file = $tag[$type . 'ball_url'];
        try {
            $request = $this->httpClient->get($file);
            $response = $request->send();

            return $response->getBody();
        } catch (RequestException $e) {
            throw new RuntimeException(sprintf('Cannot open file %s (%s).', $file, $e->getMessage()));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * {@inheritDoc}
     */
    public function setTag(array $tag)
    {
        $this->tag = $tag;
    }

    /**
     * {@inheritDoc}
     */
    public function getTags()
    {
        list($repoUser, $repoName) = explode('/', $this->clearGitURL($this->url));
        try {
            $githubTagsURL = sprintf('https://api.github.com/repos/%s/%s/tags', $repoUser, $repoName);
            $request = $this->httpClient->get($githubTagsURL);
            $response = $request->send();
            $tags = json_decode($response->getBody(true), true);

            return array_map(function ($var) {
                return $var['name'];
            }, $tags);
        } catch (RequestException $e) {
            throw new RuntimeException(sprintf('Cannot open repo %s/%s (%s).', $repoUser, $repoName, $e->getMessage()));
        }
    }

    /**
     * @param  string $version
     * @return string
     */
    private function fixVersion($version)
    {
        $bits = explode('.', $version);
        if (substr($version, 0, 2) == '>=') {
            if (count($bits) == 3) {
                array_pop($bits);
                $version = implode('.', $bits);
                $version = substr($version, 2) . '.*';
            } else {
                $version = substr($version, 2) . '.*';
            }
        } else {
            if (count($bits) == 1) {
                $version = $version . '.*.*';
            } elseif (count($bits) == 2) {
                $version = $version . '.*';
            }
        }

        return trim($version);
    }

    /**
     * @param  string
     * @return string
     */
    private function clearGitURL($url)
    {
        if (substr($url, 0, 6) == 'git://') {
            $url = substr($url, 6);
        }
        if (substr($url, 0, 8) == 'https://') {
            $url = substr($url, 8);
        }
        if (substr($url, 0, 11) == 'github.com/') {
            $url = substr($url, 11);
        } elseif (substr($url, 0, 15) == 'raw.github.com/') {
            $url = substr($url, 15);
        }
        if (substr($url, -4) == '.git') {
            $url = substr($url, 0, -4);
        }

        return $url;
    }

}
