<?php
/**
 * @package    Sds
 * @license    MIT
 */
namespace Sds\DoctrineExtensionsModule\Controller;

use Sds\DoctrineExtensions\Accessor\Accessor;
use Sds\DoctrineExtensionsModule\Exception\InvalidArgumentException;
use Sds\DoctrineExtensionsModule\Exception\DocumentNotFoundException;
use Sds\DoctrineExtensionsModule\Options\JsonRestfulController as Options;
use Sds\Zf2ExtensionsModule\Controller\AbstractJsonRestfulController;
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

        $totalQuery = $queryBuilder
            ->find($this->options->getDocumentClass());

        foreach($this->getCriteria() as $field => $value){
            $totalQuery->field($field)->equals($value);
        }

        $total = $totalQuery
            ->getQuery()
            ->execute()
            ->count();

        if ($total == 0){
            $this->response->getHeaders()->addHeader(ContentRange::fromString("Content-Range: 0-0/0"));
            return $this->model->setVariables([]);
        }

        $offset = $this->getOffset();

        $resultsQuery = $queryBuilder
            ->find($this->options->getDocumentClass());

        foreach($this->getCriteria() as $field => $value){
            $resultsQuery->field($field)->equals($value);
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

        return $this->model->setVariables($results);
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

        return $this->model->setVariables($this->options->getSerializer()->applySerializeMetadataToArray($result, $class));
    }

    public function create($data){

        $class = $this->options->getDocumentClass();
        $documentManager = $this->options->getDocumentManager();
        $serializer = $this->options->getSerializer();

        $document = $serializer->fromArray($data, null, $class);
        $documentValidator = $this->options->getDocumentValidator();
        $documentValidator->setDocumentManager($documentManager);
        $validatorResult = $documentValidator->isValid($document, $documentManager->getClassMetadata($class));

        if ( ! $validatorResult->getResult()){
            throw new InvalidArgumentException(implode(', ', $validatorResult->getMessages()));
        }

        $documentManager->persist($document);
        $documentManager->flush();

        return $this->model->setVariables($serializer->toArray($document));
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

        $documentValidator = $this->options->getDocumentValidator();
        $documentValidator->setDocumentManager($documentManager);
        $validatorResult = $documentValidator->isValid($document, $metadata);

        if ( ! $validatorResult->getResult()) {
            $documentManager->detach($document);
            throw new InvalidArgumentException(implode(', ', $validatorResult->getMessages()));
        }

        $documentManager->flush();

        return $this->model->setVariables($this->options->getSerializer()->toArray($document));
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

            if ($limit < $this->options->getLimit() && $limit != 0) {
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
        $metadata = $this->options->getDocumentManager()->getClassMetadata($this->options->getDocumentClass());
        foreach ($this->request->getQuery() as $key => $value){
            //ignore criteria that null and for fields that don't exist
            if (isset($value) && array_key_exists($key, $metadata->reflFields)){
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected function getSort(){

        foreach ($this->request->getQuery() as $key => $value){
            if (substr($key, 0, 4) == 'sort' && ! isset($value)){
                $sort = $key;
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

