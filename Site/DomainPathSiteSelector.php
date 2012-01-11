<?php
/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\PageBundle\Site;

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

use Sonata\PageBundle\Model\SiteManagerInterface;
use Sonata\PageBundle\Model\SiteInterface;
use Sonata\PageBundle\Request\SiteRequestInterface;
use Sonata\PageBundle\Request\SiteRequestContext;

class DomainPathSiteSelector extends BaseSiteSelector
{
     /**
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     * @return void
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->getRequest() instanceof SiteRequestInterface) {
            throw new \RuntimeException('You must change the main Request object in the front controller (app.php) in order to use the `domain_with_path` strategy');
        }

        $this->setRequest($event->getRequest());

        $now = new \DateTime;
        foreach ($this->getSites() as $site) {
            if ($site->getEnabledFrom()->format('U') > $now->format('U')) {
                continue;
            }

            if ($now->format('U') > $site->getEnabledTo()->format('U') ) {
                continue;
            }

            $results = array();

            if (!preg_match(sprintf('@^(%s)(.*|)@', $site->getRelativePath()), $event->getRequest()->getPathInfo(), $results)) {
                continue;
            }

            $event->getRequest()->setPathInfo($results[2] ?: '/');

            $this->site = $site;

            break;
        }

        if (!$this->site) {
            throw new \RuntimeException('Unable to retrieve the current website');
        }
    }

    /**
     * This method hijack the path generated by the Generator cache file to use
     * the relative path from the current active site.
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     * @return void
     */
    public function onKernelRequestRedirect(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$this->site) {
            return;
        }

        if ('Symfony\\Bundle\\FrameworkBundle\\Controller\\RedirectController::urlRedirectAction' == $request->get('_controller')) {
            $request->attributes->set('path', $this->site->getRelativePath().$request->attributes->get('path'));
        }
    }

    /**
     * @return \Symfony\Component\Routing\RequestContext
     */
    public function getRequestContext()
    {
        return new SiteRequestContext($this);
    }
}