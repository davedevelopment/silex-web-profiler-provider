<?php

namespace DaveDevelopment\WebProfilerProvider;

use Silex\Application;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class LazySecurityContext implements SecurityContextInterface
{
    protected $app;
    protected $id;

    public function __construct(Application $app, $id = 'security')
    {
        $this->app = $app;
        $this->id  = $id;
    }
    
    /**
     * Returns the current security token.
     *
     * @return TokenInterface|null A TokenInterface instance or null if no authentication information is available
     */
    public function getToken()
    {
        return $this->app[$this->id]->getToken();
    }

    /**
     * Sets the authentication token.
     *
     * @param TokenInterface $token
     */
    public function setToken(TokenInterface $token = null)
    {
        return $this->app[$this->id]->setToken($token);
    }

    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied object.
     *
     * @param array $attributes
     * @param mixed $object
     *
     * @return Boolean
     */
    public function isGranted($attributes, $object = null)
    {
        return $this->app[$this->id]->isGranted($attributes, $object);
    }
}
