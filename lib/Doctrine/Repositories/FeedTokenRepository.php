<?php

namespace Repositories;

use Doctrine\ORM\EntityRepository;
use Entities\Feed;
use Entities\FeedToken;

/**
 * FeedTokenRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class FeedTokenRepository extends EntityRepository
{
    /**
     * Finds a FeedToken based on given Feed and User_Adapter.
     *
     * @param Feed          $feed
     * @param \User_Adapter $user
     *
     * @return FeedToken
     */
    public function findByFeedAndUser(Feed $feed, \User_Adapter $user)
    {
        $dql = 'SELECT t
            FROM Entities\FeedToken t
            WHERE t.feed = :feed
            AND t.usr_id = :usr_id';

        $query = $this->_em->createQuery($dql);
        $query->setParameters(array(
            ':feed' => $feed,
            ':usr_id' => $user->get_id())
        );

        return $query->getOneOrNullResult();
    }
}
