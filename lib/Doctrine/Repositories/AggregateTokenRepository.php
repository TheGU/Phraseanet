<?php

namespace Repositories;

use Doctrine\ORM\EntityRepository;
use Entities\AggregateToken;

/**
 * AggregateTokenRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AggregateTokenRepository extends EntityRepository
{
    /**
     * Finds an AggregateToken based on given User_Adapter.
     *
     * @param \User_Adapter $user
     *
     * @return AggregateToken
     */
    public function findByUser(\User_Adapter $user)
    {
        $dql = 'SELECT t
            FROM Entities\AggregateToken t
            WHERE t.usr_id = :usr_id';

        $query = $this->_em->createQuery($dql);
        $query->setParameters(array(
            ':usr_id' => $user->get_id())
        );

        return $query->getOneOrNullResult();
    }
}
