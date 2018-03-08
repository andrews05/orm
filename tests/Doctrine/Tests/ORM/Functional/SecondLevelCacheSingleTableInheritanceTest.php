<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\ORM\PersistentCollection;
use Doctrine\Tests\Models\Cache\Attraction;
use Doctrine\Tests\Models\Cache\Bar;
use Doctrine\Tests\Models\Cache\Beach;
use Doctrine\Tests\Models\Cache\City;
use Doctrine\Tests\Models\Cache\Restaurant;
use function count;
use function get_class;

/**
 * @group DDC-2183
 */
class SecondLevelCacheSingleTableInheritanceTest extends SecondLevelCacheAbstractTest
{
    public function testUseSameRegion()
    {
        $attractionRegion = $this->cache->getEntityCacheRegion(Attraction::class);
        $restaurantRegion = $this->cache->getEntityCacheRegion(Restaurant::class);
        $beachRegion      = $this->cache->getEntityCacheRegion(Beach::class);
        $barRegion        = $this->cache->getEntityCacheRegion(Bar::class);

        self::assertEquals($attractionRegion->getName(), $restaurantRegion->getName());
        self::assertEquals($attractionRegion->getName(), $beachRegion->getName());
        self::assertEquals($attractionRegion->getName(), $barRegion->getName());
    }

    public function testPutOnPersistSingleTableInheritance()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();

        $this->em->clear();

        self::assertTrue($this->cache->containsEntity(Bar::class, $this->attractions[0]->getId()));
        self::assertTrue($this->cache->containsEntity(Bar::class, $this->attractions[1]->getId()));
    }

    public function testCountaisRootClass()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();

        $this->em->clear();

        foreach ($this->attractions as $attraction) {
            self::assertTrue($this->cache->containsEntity(Attraction::class, $attraction->getId()));
            self::assertTrue($this->cache->containsEntity(get_class($attraction), $attraction->getId()));
        }
    }

    public function testPutAndLoadEntities()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();

        $this->em->clear();

        $this->cache->evictEntityRegion(Attraction::class);

        $entityId1 = $this->attractions[0]->getId();
        $entityId2 = $this->attractions[1]->getId();

        self::assertFalse($this->cache->containsEntity(Attraction::class, $entityId1));
        self::assertFalse($this->cache->containsEntity(Attraction::class, $entityId2));
        self::assertFalse($this->cache->containsEntity(Bar::class, $entityId1));
        self::assertFalse($this->cache->containsEntity(Bar::class, $entityId2));

        $entity1 = $this->em->find(Attraction::class, $entityId1);
        $entity2 = $this->em->find(Attraction::class, $entityId2);

        self::assertTrue($this->cache->containsEntity(Attraction::class, $entityId1));
        self::assertTrue($this->cache->containsEntity(Attraction::class, $entityId2));
        self::assertTrue($this->cache->containsEntity(Bar::class, $entityId1));
        self::assertTrue($this->cache->containsEntity(Bar::class, $entityId2));

        self::assertInstanceOf(Attraction::class, $entity1);
        self::assertInstanceOf(Attraction::class, $entity2);
        self::assertInstanceOf(Bar::class, $entity1);
        self::assertInstanceOf(Bar::class, $entity2);

        self::assertEquals($this->attractions[0]->getId(), $entity1->getId());
        self::assertEquals($this->attractions[0]->getName(), $entity1->getName());

        self::assertEquals($this->attractions[1]->getId(), $entity2->getId());
        self::assertEquals($this->attractions[1]->getName(), $entity2->getName());

        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();

        $entity3 = $this->em->find(Attraction::class, $entityId1);
        $entity4 = $this->em->find(Attraction::class, $entityId2);

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertInstanceOf(Attraction::class, $entity3);
        self::assertInstanceOf(Attraction::class, $entity4);
        self::assertInstanceOf(Bar::class, $entity3);
        self::assertInstanceOf(Bar::class, $entity4);

        self::assertNotSame($entity1, $entity3);
        self::assertEquals($entity1->getId(), $entity3->getId());
        self::assertEquals($entity1->getName(), $entity3->getName());

        self::assertNotSame($entity2, $entity4);
        self::assertEquals($entity2->getId(), $entity4->getId());
        self::assertEquals($entity2->getName(), $entity4->getName());
    }

    public function testQueryCacheFindAll()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();

        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();
        $dql        = 'SELECT a FROM Doctrine\Tests\Models\Cache\Attraction a';
        $result1    = $this->em->createQuery($dql)
            ->setCacheable(true)
            ->getResult();

        self::assertCount(count($this->attractions), $result1);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        $this->em->clear();

        $result2 = $this->em->createQuery($dql)
            ->setCacheable(true)
            ->getResult();

        self::assertCount(count($this->attractions), $result2);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        foreach ($result2 as $entity) {
            self::assertInstanceOf(Attraction::class, $entity);
        }
    }

    public function testShouldNotPutOneToManyRelationOnPersist()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();

        $this->em->clear();

        foreach ($this->cities as $city) {
            self::assertTrue($this->cache->containsEntity(City::class, $city->getId()));
            self::assertFalse($this->cache->containsCollection(City::class, 'attractions', $city->getId()));
        }

        foreach ($this->attractions as $attraction) {
            self::assertTrue($this->cache->containsEntity(Attraction::class, $attraction->getId()));
        }
    }

    public function testOneToManyRelationSingleTable()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();

        $this->cache->evictEntityRegion(City::class);
        $this->cache->evictEntityRegion(Attraction::class);
        $this->cache->evictCollectionRegion(City::class, 'attractions');

        $this->em->clear();

        $entity = $this->em->find(City::class, $this->cities[0]->getId());

        self::assertInstanceOf(City::class, $entity);
        self::assertInstanceOf(PersistentCollection::class, $entity->getAttractions());
        self::assertCount(2, $entity->getAttractions());

        $ownerId    = $this->cities[0]->getId();
        $queryCount = $this->getCurrentQueryCount();

        self::assertTrue($this->cache->containsEntity(City::class, $ownerId));
        self::assertTrue($this->cache->containsCollection(City::class, 'attractions', $ownerId));

        self::assertInstanceOf(Bar::class, $entity->getAttractions()->get(0));
        self::assertInstanceOf(Bar::class, $entity->getAttractions()->get(1));
        self::assertEquals($this->attractions[0]->getName(), $entity->getAttractions()->get(0)->getName());
        self::assertEquals($this->attractions[1]->getName(), $entity->getAttractions()->get(1)->getName());

        $this->em->clear();

        $entity = $this->em->find(City::class, $ownerId);

        self::assertInstanceOf(City::class, $entity);
        self::assertInstanceOf(PersistentCollection::class, $entity->getAttractions());
        self::assertCount(2, $entity->getAttractions());

        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        self::assertInstanceOf(Bar::class, $entity->getAttractions()->get(0));
        self::assertInstanceOf(Bar::class, $entity->getAttractions()->get(1));
        self::assertEquals($this->attractions[0]->getName(), $entity->getAttractions()->get(0)->getName());
        self::assertEquals($this->attractions[1]->getName(), $entity->getAttractions()->get(1)->getName());
    }

    public function testQueryCacheShouldBeEvictedOnTimestampUpdate()
    {
        $this->loadFixturesCountries();
        $this->loadFixturesStates();
        $this->loadFixturesCities();
        $this->loadFixturesAttractions();
        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();
        $dql        = 'SELECT attraction FROM Doctrine\Tests\Models\Cache\Attraction attraction';

        $result1 = $this->em->createQuery($dql)
            ->setCacheable(true)
            ->getResult();

        self::assertCount(count($this->attractions), $result1);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        $contact = new Beach(
            'Botafogo',
            $this->em->find(City::class, $this->cities[1]->getId())
        );

        $this->em->persist($contact);
        $this->em->flush();
        $this->em->clear();

        $queryCount = $this->getCurrentQueryCount();

        $result2 = $this->em->createQuery($dql)
            ->setCacheable(true)
            ->getResult();

        self::assertCount(count($this->attractions) + 1, $result2);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        foreach ($result2 as $entity) {
            self::assertInstanceOf(Attraction::class, $entity);
        }
    }
}
