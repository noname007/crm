<?php

namespace OroCRM\Bundle\MagentoBundle\Entity\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Oro\Bundle\DashboardBundle\Helper\DateHelper;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Bundle\EntityBundle\Exception\InvalidEntityException;

use OroCRM\Bundle\MagentoBundle\Entity\Cart;
use OroCRM\Bundle\MagentoBundle\Entity\Customer;
use OroCRM\Bundle\MagentoBundle\Entity\Order;

class OrderRepository extends EntityRepository
{
    /**
     * @param \DateTime $start
     * @param \DateTime $end
     * @param AclHelper $aclHelper
     * @return int
     */
    public function getRevenueValueByPeriod(\DateTime $start, \DateTime $end, AclHelper $aclHelper)
    {
        $select = 'SUM(
             CASE WHEN orders.subtotalAmount IS NOT NULL THEN orders.subtotalAmount ELSE 0 END -
             CASE WHEN orders.discountAmount IS NOT NULL THEN orders.discountAmount ELSE 0 END
             ) as val';
        $qb    = $this->createQueryBuilder('orders');
        $qb->select($select)
            ->andWhere($qb->expr()->between('orders.createdAt', ':dateStart', ':dateEnd'))
            ->setParameter('dateStart', $start)
            ->setParameter('dateEnd', $end);

        $value = $aclHelper->apply($qb)->getOneOrNullResult();

        return $value['val'] ? : 0;
    }

    /**
     * @param \DateTime $start
     * @param \DateTime $end
     * @param AclHelper $aclHelper
     * @return int
     */
    public function getOrdersNumberValueByPeriod(\DateTime $start, \DateTime $end, AclHelper $aclHelper)
    {
        $qb    = $this->createQueryBuilder('o');
        $qb->select('count(o.id) as val')
            ->andWhere($qb->expr()->between('o.createdAt', ':dateStart', ':dateEnd'))
            ->setParameter('dateStart', $start)
            ->setParameter('dateEnd', $end);

        $value = $aclHelper->apply($qb)->getOneOrNullResult();

        return $value['val'] ? : 0;
    }

    /**
     * @param \DateTime $start
     * @param \DateTime $end
     * @param AclHelper $aclHelper
     * @return int
     */
    public function getAOVValueByPeriod(\DateTime $start, \DateTime $end, AclHelper $aclHelper)
    {
        $select = 'SUM(
             CASE WHEN o.subtotalAmount IS NOT NULL THEN o.subtotalAmount ELSE 0 END -
             CASE WHEN o.discountAmount IS NOT NULL THEN o.discountAmount ELSE 0 END
             ) as revenue,
             count(o.id) as ordersCount';
        $qb    = $this->createQueryBuilder('o');
        $qb->select($select)
            ->andWhere($qb->expr()->between('o.createdAt', ':dateStart', ':dateEnd'))
            ->setParameter('dateStart', $start)
            ->setParameter('dateEnd', $end);

        $value = $aclHelper->apply($qb)->getOneOrNullResult();

        return $value['revenue'] ? $value['revenue'] / $value['ordersCount'] : 0;
    }

    /**
     * @param \DateTime $start
     * @param \DateTime $end
     * @param AclHelper $aclHelper
     * @return float|int
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getDiscountedOrdersPercentByDatePeriod(
        \DateTime $start,
        \DateTime $end,
        AclHelper $aclHelper
    ) {
        $qb = $this->createQueryBuilder('o');
        $qb->select(
            'COUNT(o.id) as allOrders',
            'SUM(CASE WHEN o.discountAmount > 0 THEN 1 ELSE 0 END) as discountedOrders'
        );
        $qb->andWhere($qb->expr()->between('o.createdAt', ':dateStart', ':dateEnd'));
        $qb->setParameter('dateStart', $start);
        $qb->setParameter('dateEnd', $end);

        $value = $aclHelper->apply($qb)->getOneOrNullResult();
        return $value['allOrders'] ? $value['discountedOrders'] / $value['allOrders'] : 0;
    }

    /**
     * @param Cart|Customer $item
     * @param string        $field
     *
     * @return Cart|Customer|null $item
     * @throws InvalidEntityException
     */
    public function getLastPlacedOrderBy($item, $field)
    {
        if (!($item instanceof Cart) && !($item instanceof Customer)) {
            throw new InvalidEntityException();
        }
        $qb = $this->createQueryBuilder('o');
        $qb->where('o.' . $field . ' = :item');
        $qb->setParameter('item', $item);
        $qb->orderBy('o.updatedAt', 'DESC');
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Get customer orders subtotal amount
     *
     * @param Customer $customer
     * @return float
     *
     * @deprecated Use CustomerRepository::calculateLifetimeValue to get lifetime value for customer
     */
    public function getCustomerOrdersSubtotalAmount(Customer $customer)
    {
        $qb = $this->createQueryBuilder('o');

        $qb
            ->select('sum(o.subtotalAmount) as subtotal')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('o.customer', ':customer'),
                    $qb->expr()->neq($qb->expr()->lower('o.status'), ':status')
                )
            )
            ->setParameter('customer', $customer)
            ->setParameter('status', Order::STATUS_CANCELED);

        return (float)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param AclHelper  $aclHelper
     * @param \DateTime  $dateFrom
     * @param \DateTime  $dateTo
     * @param DateHelper $dateHelper
     * @return array
     */
    public function getAverageOrderAmount(
        AclHelper $aclHelper,
        \DateTime $dateFrom,
        \DateTime $dateTo,
        DateHelper $dateHelper
    ) {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getEntityManager();
        $channels      = $entityManager->getRepository('OroCRMChannelBundle:Channel')
            ->getAvailableChannelNames($aclHelper, 'magento');

        // execute data query
        $queryBuilder = $this->createQueryBuilder('o');
        $selectClause = '
            IDENTITY(o.dataChannel) AS dataChannelId,
            AVG(
                CASE WHEN o.subtotalAmount IS NOT NULL THEN o.subtotalAmount ELSE 0 END -
                CASE WHEN o.discountAmount IS NOT NULL THEN o.discountAmount ELSE 0 END
            ) as averageOrderAmount';

        $dates = $dateHelper->getDatePeriod($dateFrom, $dateTo);

        $queryBuilder->select($selectClause)
            ->andWhere($queryBuilder->expr()->between('o.createdAt', ':dateStart', ':dateEnd'))
            ->setParameter('dateStart', $dateFrom)
            ->setParameter('dateEnd', $dateTo)
            ->groupBy('dataChannelId');
        $dateHelper->addDatePartsSelect($dateFrom, $dateTo, $queryBuilder, 'o.createdAt');
        $amountStatistics = $aclHelper->apply($queryBuilder)->getArrayResult();

        $items = [];
        foreach ($amountStatistics as $row) {
            $key         = $dateHelper->getKey($dateFrom, $dateTo, $row);
            $channelId   = (int)$row['dataChannelId'];
            $channelName = $channels[$channelId]['name'];

            if (!isset($items[$channelName])) {
                $items[$channelName] = $dates;
            }
            $items[$channelName][$key]['amount'] = (float)$row['averageOrderAmount'];
        }

        // restore default keys
        foreach ($items as $channelName => $item) {
            $items[$channelName] = array_values($item);
        }

        return $items;
    }

    /**
     * @return array
     */
    protected function getOrderSliceDateAndTemplates()
    {
        // calculate slice date
        $currentYear  = (int)date('Y');
        $currentMonth = (int)date('m');

        $sliceYear  = $currentMonth === 12 ? $currentYear : $currentYear - 1;
        $sliceMonth = $currentMonth === 12 ? 1 : $currentMonth + 1;
        $sliceDate  = new \DateTime(sprintf('%s-%s-01', $sliceYear, $sliceMonth), new \DateTimeZone('UTC'));

        // calculate match for month and default channel template
        $monthMatch      = [];
        $channelTemplate = [];
        if ($sliceYear !== $currentYear) {
            for ($i = $sliceMonth; $i <= 12; $i++) {
                $monthMatch[$i]                  = ['year' => $sliceYear, 'month' => $i];
                $channelTemplate[$sliceYear][$i] = 0;
            }
        }
        for ($i = 1; $i <= $currentMonth; $i++) {
            $monthMatch[$i]                    = ['year' => $currentYear, 'month' => $i];
            $channelTemplate[$currentYear][$i] = 0;
        }

        return [$sliceDate, $monthMatch, $channelTemplate];
    }
}
