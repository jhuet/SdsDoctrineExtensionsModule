<?php
/**
 * @package    Sds
 * @license    MIT
 */
namespace Sds\DoctrineExtensionsModule\Controller;

use Sds\DoctrineExtensions\Accessor\Accessor;
use Sds\DoctrineExtensionsModule\Exception\InvalidArgumentException;
use Sds\DoctrineExtensionsModule\Exception\DocumentNotFoundException;
use Sds\DoctrineExtensionsModule\Options\AbstractJsonRestfulController as Options;
use Sds\JsonController\AbstractJsonRestfulController;
use Zend\Http\Header\ContentRange;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 *
 * @since   1.0
 * @version $Revision$
 * @author  Tim Roediger <superdweebie@gmail.com>
 */
class JsonRestfulController extends AbstractJsonRestfulController
{

    protected $options;

    public function getOptions() {
        return $this->options;
    }

    public function setOptions($options) {
        if (!$options instanceof Options) {
            $options = new Options($options);
        }
        isset($this->serviceLocator) ? $options->setServiceLocator($this->serviceLocator) : null;
        $this->options = $options;
    }

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        parent::setServiceLocator($serviceLocator);
        $this->getOptions()->setServiceLocator($serviceLocator);
    }

    public function __construct($options = null) {
        $this->setOptions($options);
    }

    public function getList(){

        $queryBuilder = $this->options->getDocumentManager()->createQueryBuilder();

        $total = $queryBuilder
            ->find($this->options->getDocumentClass())
            ->getQuery()
            ->execute()
            ->count();

        $offset = $this->getOffset();

        $resultsQuery = $queryBuilder
            ->find($this->options->getDocumentClass());

        foreach($this->getCriteria() as $field => $value){
            $resultsQuery->find($field)->equals($value);
        }

        $resultsQuery
            ->limit($this->getLimit())
            ->skip($offset);

        foreach($this->getSort() as $sort){
            $resultsQuery->sort($sort['field'], $sort['direction']);
        }

        $resultsCursor = $resultsQuery
            ->eagerCursor(true)
            ->getQuery()
            ->execute();

        foreach ($resultsCursor as $result){
            $results[] = $this->options->getSerializer()->toArray($result, $this->options->getDocumentClass());
        }

        $max = $offset + count($results);

        $this->response->getHeaders()->addHeader(ContentRange::fromString("Content-Range: $offset-$max/$total"));

        return $results;
    }

    public function get($id){

        $class = $this->options->getDocumentClass();
        $documentManager = $this->options->getDocumentManager();
        $metadata = $documentManager->getClassMetadata($class);

        $result = $documentManager
            ->createQueryBuilder()
            ->find($class)
            ->field($metadata->identifier)->equals($id)
            ->hydrate(false)
            ->getQuery()
            ->getSingleResult();

        if ( ! isset($result)){
            throw new DocumentNotFoundException(sprintf('Document with id %s could not be found in the database', $id));
        }

        return $this->options->getSerializer()->applySerializeMetadataToArray($result, $class);
    }

    public function create($data){

        $class = $this->options->getDocumentClass();
        $documentManager = $this->options->getDocumentManager();
        $serializer = $this->options->getSerializer();

        $document = $serializer->fromArray($data, null, $class);
        $validatorResult = $this->options->getDocumentValidator()
            ->isValid($document, $documentManager->getClassMetadata($class));

        if ( ! $validatorResult->getResult()){
            throw new InvalidArgumentException(implode(', ', $validatorResult->getMessages()));
        }

        $documentManager->persist($document);
        $documentManager->flush();

        return $serializer->toArray($document);
    }

    public function update($id, $data){

        $class = $this->options->getDocumentClass();
        $documentManager = $this->options->getDocumentManager();
        $metadata = $documentManager->getClassMetadata($class);

        $document = $documentManager
            ->createQueryBuilder()
            ->find($class)
            ->field($metadata->identifier)->equals($id)
            ->eagerCursor(true)
            ->getQuery()
            ->getSingleResult();

        foreach ($data as $field => $value){
            $setter = Accessor::getSetter($metadata, $field, $document);
            $document->$setter($value);
        }

        $validatorResult = $this->options->getDocumentValidator()
            ->isValid($document, $metadata);

        if ( ! $validatorResult->getResult()) {
            $documentManager->detach($document);
            throw new InvalidArgumentException(implode(', ', $validatorResult->getMessages()));
        }

        $documentManager->flush();

        return $this->options->getSerializer()->toArray($document);
    }

    public function delete($id){

        $class = $this->options->getDocumentClass();
        $documentManager = $this->options->getDocumentManager();
        $metadata = $documentManager->getClassMetadata($class);

        $documentManager
            ->createQueryBuilder($class)
            ->remove()
            ->field($metadata->identifier)->equals($id)
            ->getQuery()
            ->execute();
    }

    protected function getLimit(){

        $range = $this->getRequest()->getHeader('Range');

        if ($range) {
            $values = explode('-', explode('=', $range->getFieldValue())[1]);
            $limit = intval($values[1]) - intval($values[0]) + 1;
            if ($limit < $this->options->getLimit()) {
                return $limit;
            }
        }
        return $this->options->getLimit();
    }

    protected function getOffset(){

        $range = $this->getRequest()->getHeader('Range');

        if($range){
            return intval(explode('-', explode('=', $range->getFieldValue())[1])[0]);
        } else {
            return 0;
        }
    }

    protected function getCriteria(){

        $result = [];

        foreach ($this->request->getQuery() as $key => $value){
            if (isset($value)){
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected function getSort(){

        foreach ($this->request->getQuery() as $key => $value){
            if (substr($key, 0, 4) == 'sort' && ! isset($value)){
                $sort = $key;
                break;
            }
        }

        if ( ! isset($sort)){
            return [];
        }

        $sortFields = explode(',', str_replace(')', '', str_replace('sort(', '', $sort)));
        $return = [];

        foreach ($sortFields as $value)
        {
            $return[] = [
                'field' => substr($value, 1),
                'direction' => substr($value, 0, 1) == '+' ? 'asc' : 'desc'
            ];
        }

        return $return;
    }
}

