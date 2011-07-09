<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/**
 * A Photon view is simply a method of a class with a specific signature.
 */

namespace helloworld\views;

/**
 * The class containing the views.
 *
 * The name of the class does not matter at all. You can create
 * several class to group your views, for example "Base", "Admin",
 * "ControlPanel". As long as you define your views as methods of a
 * class with the right signature, it works.
 */
class Views
{
    /**
     * A very simple Hello World! view.
     *
     */
    public function index($request, $match)
    {
        return new \photon\http\Response("Hello World!\n");
    }

    /**
     * A bit more complex as using the matched string in the url for the name.
     *
     * In a template, the name can be automatically escaped, not here,
     * so the escaping is done manually.
     */
    public function you($request, $match)
    {
        $name = \photon\esc($match[1]);
        return new \photon\http\Response(sprintf("Hello %s!\n", $name));
    }
}
