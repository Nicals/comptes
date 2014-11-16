<?php

namespace Comptes\Bundle\CoreBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Comptes\Bundle\CoreBundle\Entity\Plein;
use Comptes\Bundle\CoreBundle\Entity\Vehicule;

/**
 * PleinRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PleinRepository extends EntityRepository
{
    /**
     * {@inheritDoc}
     */
    public function findAll()
    {
        return $this->findBy(array(), array('date' => 'DESC'));
    }

    /**
     * Récupère les pleins d'un véhicule.
     *
     * @param Vehicule $vehicule
     * @param string $order 'ASC' (par défaut) ou 'DESC'.
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function findByVehicule(Vehicule $vehicule, $order='ASC')
    {
        $queryBuilder = $this->_em->createQueryBuilder();

        $vehiculeID = $vehicule->getId();

        $queryBuilder
            ->select('p')
            ->from('ComptesCoreBundle:Plein', 'p')
            ->where('p.vehicule = :vehicule_id')
            ->orderBy('p.date', $order)
            ->setParameter(':vehicule_id', $vehiculeID);

        $pleins = $queryBuilder->getQuery()->getResult();

        return $pleins;
    }

    /**
     * Récupère le plein le plus récent.
     *
     * @return Plein
     */
    public function findLatestOne()
    {
        $queryBuilder = $this->_em->createQueryBuilder();

        $queryBuilder
            ->select('p')
            ->from('ComptesCoreBundle:Plein', 'p')
            ->orderBy('p.date', 'DESC')
            ->setMaxResults(1);

        try
        {
            $plein = $queryBuilder->getQuery()->getSingleResult();
        }
        catch (\Doctrine\ORM\NoResultException $exception)
        {
            $plein = null;
        }

        return $plein;
    }
}