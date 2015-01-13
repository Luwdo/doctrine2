<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ORM\Persisters;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;

/**
 * Persister for one-to-many collections.
 *
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Alexander <iam.asm89@gmail.com>
 * @since   2.0
 */
class OneToManyPersister extends AbstractCollectionPersister
{
    /**
     * {@inheritdoc}
     */
    public function delete(PersistentCollection $coll)
    {
        // This can never happen. One to many can only be inverse side.
        // For owning side one to many, it is required to have a join table,
        // then classifying it as a ManyToManyPersister.
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function update(PersistentCollection $coll)
    {
        // This can never happen. One to many can only be inverse side.
        // For owning side one to many, it is required to have a join table,
        // then classifying it as a ManyToManyPersister.
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function get(PersistentCollection $coll, $index)
    {
        $mapping   = $coll->getMapping();
        $persister = $this->uow->getEntityPersister($mapping['targetEntity']);

        if (!isset($mapping['indexBy'])) {
            throw new \BadMethodCallException("Selecting a collection by index is only supported on indexed collections.");
        }

        return $persister->load(
            array(
                $mapping['mappedBy'] => $coll->getOwner(),
                $mapping['indexBy']  => $index
            ),
            null,
            null,
            array(),
            null,
            1
        );
    }

    /**
     * {@inheritdoc}
     */
    public function count(PersistentCollection $coll)
    {
        $mapping   = $coll->getMapping();
        $persister = $this->uow->getEntityPersister($mapping['targetEntity']);

        // only works with single id identifier entities. Will throw an
        // exception in Entity Persisters if that is not the case for the
        // 'mappedBy' field.
        $criteria = new Criteria(Criteria::expr()->eq($mapping['mappedBy'], $coll->getOwner()));

        return $persister->count($criteria);
    }

    /**
     * {@inheritdoc}
     */
    public function slice(PersistentCollection $coll, $offset, $length = null)
    {
        $mapping   = $coll->getMapping();
        $persister = $this->uow->getEntityPersister($mapping['targetEntity']);

        return $persister->getOneToManyCollection($mapping, $coll->getOwner(), $offset, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function containsKey(PersistentCollection $coll, $key)
    {
        $mapping   = $coll->getMapping();
        $persister = $this->uow->getEntityPersister($mapping['targetEntity']);

        // only works with single id identifier entities. Will throw an
        // exception in Entity Persisters if that is not the case for the
        // 'mappedBy' field.
        $criteria = new Criteria();

        $criteria->andWhere(Criteria::expr()->eq($mapping['mappedBy'], $coll->getOwner()));
        $criteria->andWhere(Criteria::expr()->eq($mapping['indexBy'], $key));

        return (bool) $persister->count($criteria);
    }

     /**
     * {@inheritdoc}
     */
    public function contains(PersistentCollection $coll, $element)
    {
        if ( ! $this->isValidEntityState($element)) {
            return false;
        }

        $mapping   = $coll->getMapping();
        $persister = $this->uow->getEntityPersister($mapping['targetEntity']);

        // only works with single id identifier entities. Will throw an
        // exception in Entity Persisters if that is not the case for the
        // 'mappedBy' field.
        $criteria = new Criteria(Criteria::expr()->eq($mapping['mappedBy'], $coll->getOwner()));

        return $persister->exists($element, $criteria);
    }

    /**
     * {@inheritdoc}
     */
    public function removeElement(PersistentCollection $coll, $element)
    {
        if ( ! $this->isValidEntityState($element)) {
            return false;
        }

        $mapping   = $coll->getMapping();
        $persister = $this->uow->getEntityPersister($mapping['targetEntity']);

        return $persister->delete($element);
    }

    /**
     * {@inheritdoc}
     */
    public function loadCriteria(PersistentCollection $collection, Criteria $criteria)
    {
        throw new \BadMethodCallException("Filtering a collection by Criteria is not supported by this CollectionPersister.");
    }

    /**
     * Check if entity is in a valid state for operations.
     *
     * @param $entity
     *
     * @return bool
     */
    private function isValidEntityState($entity)
    {
        $entityState = $this->uow->getEntityState($entity, UnitOfWork::STATE_NEW);

        if ($entityState === UnitOfWork::STATE_NEW) {
            return false;
        }

        // If Entity is scheduled for inclusion, it is not in this collection.
        // We can assure that because it would have return true before on array check
        if ($entityState === UnitOfWork::STATE_MANAGED && $this->uow->isScheduledForInsert($entity)) {
            return false;
        }

        return true;
    }
}
