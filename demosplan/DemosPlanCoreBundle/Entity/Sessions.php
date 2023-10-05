<?php

namespace demosplan\DemosPlanCoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * This entity is only used to create the table for the session handler.
 * It is not actively used in the application.
 *
 * @ORM\Entity
 * @ORM\Table(name="sessions")
 * @ORM\Table(indexes={@ORM\Index(name="sessions_sess_lifetime_idx", columns={"sess_lifetime"})})
 */
class Sessions
{
    /**
     *
     * @ORM\Id
     * @ORM\Column(type="binary", length=128, nullable=false)
     */
    private string $sess_id;

    /**
     * @ORM\Column(type="blob", nullable=false, options={"fixed" = true, "length" = 16777215})
     */
    private $sess_data;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"unsigned"=true})
     */
    private $sess_lifetime;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"unsigned"=true})
     */
    private $sess_time;

}
