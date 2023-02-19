## Laminas DB Wrapper

Library wrapper for Laminas DB

### RepositoryAbstractServiceFactory

Lazy repository Initiation for Laminas ServiceManager.
Any service request end with Repository will be created automatically.

To use it put in the service_factory in service config

```php
return [
    "service" => [
        "abstract_factories" => [
            Itseasy\Repository\Factory\RepositoryAbstractServiceFactory::class
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
getFilterAwareRows(
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
) : int;

# Delete Record base on filter given
# Filter will be converted to sql by predefined filter
filterAwareDelete(string $filters = null) : ResultInterface;
```

### SqlFilter

A helper class to adding filter sql query base on definition

#### RegexSqlFilter

Using regex base sql filter.
Value inside round bracket ( **(...)** ) in regex will be pass to callback function as argument
All callback must return **Laminas\Db\Sql\Predicate\PredicateInterface**

All filter is running in orderly manner from top to bottom

Example

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
                "is:active", function ($status) {
                    $p = new Predicate();
                    return $p->equalTo("active", true);
                }
            ],
            [
                "id:(\d)", function ($id) {
                    $p = new Predicate();
                    return $p->equalTo("id", $id);
                }
            ],
            [
                "tech_creation_date:(\d{4}-\d{2}-\d{2}):(\d{4}-\d{2}-\d{2})", function($start_date, $end_date) {
                    return new Laminas\Db\Sql\Predicate\Between(
                        "tech_creation_date",
                        $start_date,
                        $end_date
                    );
                }
            ],
            [
                "([a-z0-9]+)", function($value) {
                    $value = str_replace(" ", "%", $value);
                	return new Laminas\Db\Sql\PredicateSet([
 	                    new Lamainas|Db\Sql\Predicate\Like("first_name", "%$value%"),
                        new Lamainas|Db\Sql\Predicate\Like("last_name", "%$value%"),
 			            new Lamainas|Db\Sql\Predicate\Like("email", "%$value%"),
                    ], Laminas\Db\Sql\Predicate\PredicateSet::COMBINED_BY_AND );
                }
            ]
        ]));
    }
}

# Usage
$repository = new Repository($db, "mytable");
$repository->getFilterAwareRows("id:12", "id DESC", 0, 10);
$repository->getFilterAwareRows("tech_creation_date:2022-01-01:2022-03-01", null, 0, 10);
$repository->getFilterAwareRows("is:active somebody");

```
