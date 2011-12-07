<?php
/** 
 * jsSocket URLs need to be hooked in the URL map at the root level,
 * that is, you cannot have a prefix before the @. This is why we
 * include directly the views at the root of the project.
 */
return array(
             array('regex' => '#^@photonchat#',
                   'view' => array('\photonchat\views\Chat', 'chatbox'),
                   'name' => 'photonchat_chatbox'
                   ),
             // Mongrel2 sends system messages for the disconnection
             // on the @* route.
             array('regex' => '#^@\*#',
                   'view' => array('\photonchat\views\Chat', 'chatbox'),
                   'name' => 'photonchat_chatbox_system'
                   ),
             );

