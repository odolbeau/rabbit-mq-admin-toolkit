# RabbitMQ Tools

## Installation

The recommended way to install RabbitMQ Tools is through
[Composer](http://getcomposer.org/). Require the
`odolbeau/rabbit-mq-admin-toolkit` package into your `composer.json` file:

```json
{
    "require": {
        "odolbeau/rabbit-mq-admin-toolkit": "@stable"
    }
}
```

**Protip:** you should browse the
[`odolbeau/rabbit-mq-admin-toolkit`](https://packagist.org/packages/odolbeau/rabbit-mq-admin-toolkit)
page to choose a stable version to use, avoid the `@stable` meta constraint.

## Usage

You can create / update vhosts with the following command:

    ./rabbit vhost:mapping:create conf/vhost/events.yml

You can change all connection informations with options. Launch `./console
vhost:create -h` to have more informations.

You can launch the vhost creation even if the vhost already exist. Nothing will
be deleted (and it will not impact workers).

## Configuration

You can use the followings parameters for configuring an exchange:

* `with dl`: if set to true, all queues in the current vhost will be
  automatically configured to have a dl (with name: `{queueName}_dl`). Of
  course, the exchange `dl` will be created.
* `with_unroutable`: is set to true, an `unroutable` exchange will be created
  and all  others ones will be configured to move unroutable messages to this
  one. The `unroutable` exchange is a fanout exchange and a `unroutable` queue
  is bind on it.

## Example

```yaml
my_vhost_name:

    permissions:
        my_user:
            configure: amq\.gen.*
            read: .*
            write: .*
            
    parameters:
        with_dl: true # If true, all queues will have a dl and the corresponding mapping with the exchange "dl"
        with_unroutable: true # If true, all exchange will be declared with an unroutable config

    exchanges:
        my_exchange:
            type: direct
            durable: true
            with_unroutable: true #if true, unroutable exchange will be created (if not already set as global parameter)

        my_exchange_headers:
            type: headers
            durable: true

    queues:
        my_queue:
            durable: true
            delay: 5000 #create delayed message queue (value is in milliseconds)
            bindings:
                - 
                    exchange: my_exchange
                    routing_key: my_routing_key
                - 
                    exchange: my_exchange
                    routing_key: other_routing_key
                    
        another_queue:
            durable: true
            with_dl: false
            retries: [25, 125, 625]
            bindings:
                - 
                    exchange: my_exchange_headers
                    x-match: all
                    matches: {header_name: value, other_header_name: some_value}
```

## License

This project is released under the MIT License. See the bundled LICENSE file
for details.

## Changelog

### BC breaks between 1.x and 2.0.x

  * Short binding syntax is no more supported. 
```yaml
  # old syntax
  queues:
    my_queue:
        bindings:
            - my_exchange:my_routing_key
```
must be replaced by
```yaml
  # new syntax
  queues:
    my_queue:
        bindings:
            - 
                exchange: my_exchange
                routing_key: my_routing_key
```

