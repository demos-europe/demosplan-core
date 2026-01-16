<?php

namespace demosplan\DemosPlanCoreBundle\ApiResources\Serializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class PlainIdJsonApiNormalizer implements
    NormalizerInterface,
    DenormalizerInterface,
    SerializerAwareInterface
{
    private $decorated;

    public function __construct(NormalizerInterface $decorated)
    {
        if (!$decorated instanceof DenormalizerInterface) {
            throw new \InvalidArgumentException(
                'The decorated normalizer must implement DenormalizerInterface.'
            );
        }
        $this->decorated = $decorated;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        // Call original normalizer
        $data = $this->decorated->normalize($object, $format, $context);

        // Modify the id field to use plain identifier
        if (is_array($data) && isset($data['data']['id'])) {
            $iri = $data['data']['id'];
            // Extract plain ID from IRI by getting last segment
            $parts = explode('/', $iri);
            $data['data']['id'] = end($parts);
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        if($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}
