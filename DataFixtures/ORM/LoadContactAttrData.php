<?php

namespace Oro\Bundle\ContactBundle\DataFixtures\ORM;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttributeType;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttribute;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

use Oro\Bundle\ContactBundle\Entity\Manager\ContactManager;

class LoadContactAttrData extends AbstractFixture implements ContainerAwareInterface
{
    /**
     * @var ContactManager
     */
    protected $fm;

    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    protected $sm;

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->fm = $this->container->get('oro_contact.manager');
        $this->sm = $this->fm->getStorageManager();
    }

    /**
     * Load sample user group data
     *
     * @param \Doctrine\Common\Persistence\ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $this->addAttributes(
            array(
                array(
                    'code'  => 'first_name',
                    'label' => 'First Name',
                ),
                array(
                    'code'  => 'last_name',
                    'label' => 'Last Name',
                ),
                array(
                    'code'  => 'name_prefix',
                    'label' => 'Name Prefix',
                ),
                array(
                    'code'  => 'title',
                    'label' => 'Title',
                ),
                array(
                    'code'  => 'birthday',
                    'type'  => 'oro_flexibleentity_date',
                    'label' => 'Birthday',
                ),
                array(
                    'code'  => 'description',
                    'type'  => 'oro_flexibleentity_textarea',
                    'label' => 'Description',
                ),
                array(
                    'code'  => 'lead_source',
                    'type'  => 'oro_flexibleentity_simpleselect',
                    'label' => 'Lead Source',
                    'options' => array(
                        'other', 'call', 'TV', 'website'
                    )
                ),
                array(
                    'code'  => 'account',
                    'type'  => 'oro_account_attribute_account',
                    'label' => 'Account',
                ),
                array(
                    'code'  => 'assigned_to',
                    'type'  => 'oro_user_attribute_user',
                    'label' => 'Assigned To'
                ),
                array(
                    'code'  => 'reports_to',
                    'type'  => 'oro_contact_attribute_contact',
                    'label' => 'Reports To'
                )
            )
        );

        $this->sm->flush();
    }

    protected function addAttributes(array $attributes)
    {
        foreach ($attributes as $data) {
            $attr = $this->createAttribute($data);

            $this->createAttributeOptions($attr, $data);
            $this->setAttributeFlags($attr, $data);
            $this->setAttributeParameters($attr, $data);

            $this->sm->persist($attr);
        }
    }

    /**
     * @param AbstractAttribute $attr
     * @param array|string $data
     */
    protected function createAttributeOptions(AbstractAttribute $attr, $data)
    {
        if (is_array($data) && array_key_exists('options', $data)) {
            foreach ($data['options'] as $option) {
                $attr->addOption(
                    $this->fm->createAttributeOption()->addOptionValue(
                        $this->fm->createAttributeOptionValue()->setValue($option)
                    )
                );
            }
        }
    }

    /**
     * @param array|string $data
     * @return AbstractAttribute
     */
    protected function createAttribute($data)
    {
        /** @var $attribute AbstractAttribute */
        $attribute = $this->fm->createAttribute($this->getType($data));
        $attribute
            ->setCode($this->getCode($data))
            ->setLabel($this->getLabel($data));

        return $attribute;
    }

    protected function setAttributeFlags(AbstractAttribute $attr, $data)
    {
        if (!is_array($data)) {
            return;
        }
        $supportedProperties = array('searchable', 'translatable', 'required', 'scopable');
        foreach ($supportedProperties as $property) {
            if (array_key_exists($property, $data)) {
                $method = 'set' . ucfirst($property);
                $attr->$method((bool)$data[$property]);
            }
        }
    }

    /**
     * @param AbstractAttribute $attr
     * @param array|string $data
     */
    protected function setAttributeParameters(AbstractAttribute $attr, $data)
    {
        if (!is_array($data)) {
            return;
        }
        $supportedProperties = array('entityType', 'attributeType', 'backendType', 'backendStorage', 'defaultValue', 'id');
        foreach ($supportedProperties as $property) {
            if (array_key_exists($property, $data)) {
                $method = 'set' . ucfirst($property);
                $attr->$method($data[$property]);
            }
        }
    }

    /**
     * Get code based on configuration
     *
     * @param $data
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getCode($data)
    {
        $code = null;
        if (is_string($data)) {
            $code = $data;
        } elseif (is_array($data) && isset($data['code'])) {
            $code = $data['code'];
        }
        if ($code === null) {
            throw new \InvalidArgumentException('Code is required for attribute');
        }
        return $code;
    }

    /**
     * Get label based on configuration
     *
     * @param $data
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getLabel($data)
    {
        $label = null;
        if (is_string($data)) {
            $label = $data;
        } elseif (is_array($data)) {
            if (isset($data['label'])) {
                $label = $data['label'];
            } elseif (isset($data['code'])) {
                $label = $data['code'];
            }
        }
        if ($label === null) {
            throw new \InvalidArgumentException('Label is required for attribute');
        }
        return $label;
    }

    /**
     * Get type based on configuration
     *
     * @param array $data
     * @return string
     */
    protected function getType($data)
    {
        return is_array($data) && array_key_exists('type', $data) ? $data['type'] : 'oro_flexibleentity_text';
    }
}
