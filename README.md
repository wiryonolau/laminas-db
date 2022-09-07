## Laminas DB Wrapper

Library wrapper for Laminas DB

### RepositoryAbstractServiceFactory

Lazy repository Initiation for Laminas ServiceManager. 
Any service request end with Repository will be created automatically.

To use it  put in the service_factory in service config

```php
return [
    "service" => [
        "abstract_factories" => [
            Itseasy\Repository\RepositoryAbstractServiceFactory::class
        ]
    ]
]
```

### GenericRepository

A Generic Repository class that will be call by RepositoryAbstractServiceFactory when use.

### AbstractRepository

Abstract Repository class. 

Any **FilterAware** function require **table** and **sqlFilter** define on construct

Available function

```php
# Get Record by identifer
getRowByIdentifier(
    $value, 
    string $identifier = "id", 
    $objectPrototype
) : ResultInterface | $objectPrototype;

# Get Multiple Record by array of condition
getRows(
    array $where = [],
	?string $orders = null,
    ?int $offset = null,
    ?int $limit = null,
    $resultSetObjectPrototype = null,
    $objectPrototype = null
) : ResultInterface | $resultSetObjectPrototype | ArrayIterator;

# Get Row Count by array of condition
getRowCount(
	array $where = []
): int;

# Delete Record base on condition
delete(array $where = []): ResultInterface;

# Insert or update existing Record base on identifier
# $model must has a getter and getArrayCopy() function to read properties
public function upsert(
   	object $model,
	string $identifier = "id",
   	array $exclude_attributes = []
) : $model;

# Get Multiple Record base on filter given
# Filter will be converted to sql by predefined filter
getFitlerAwareRows(
    string $filters = null,
    ?string $orders = null,
    ?int $offset = null,
    ?int $limit = null,
    $resultSetObjectPrototype = null,
    $objectPrototype = null
) : ResultInterface | $resultSetObjectPrototype | ArrayIterator;

# Get Record Count base on filter given
# Filter will be converted to sql by predefined filter
getFilterAwareRowCount(
    string $filters = null
) : ResultInterface;
 
# Delete Record base on filter given
# Filter will be converted to sql by predefined filter
filterAwareDelete(string $filters = null) : ResultInterface;
```

### SqlFilter

A helper class to adding filter sql query base on definition , all callback must return **Laminas\Db\Sql\Predicate\PredicateInterface**

#### RegexSqlFilter

Using regex base sql fitler.
Value inside round bracket ( **(...)** ) in regex will be pass to callback function as argument

```php
use Itseasy\Repository\AbstractRepository;
use Itseasy\Database\Sql\Filter\RegexSqlFilter;

class Repository extends AbstractRepository 
{
    # Can be public / protected function
    protected function defineSqlFilter() : void 
    {
        $this->setSqlFilter(new RegexSqlFilter([
            [
                "id:(\d)", function ($id) {
                    $p = new Predicate();
                    return $p->equalTo("id", $id);
                }
            ]
        ]));
    }
}

# Usage
$repository = new Repository($db, "mytable");
$repository->getFilterAwareRows("id:12", "id DESC", 0, 10);
```



