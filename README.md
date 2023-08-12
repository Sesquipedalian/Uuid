# A simple PHP class for working with Universally Unique Identifiers (UUIDs)

Generates, compresses, and expands Univerally Unique Identifers.

This class can generate UUIDs of versions 1 through 7.

It is also possible to create a new instance of this class from a UUID string
of any version via the Uuid::createFromString() method.

This class implements the \Stringable interface, so getting the canonical
string representation of a UUID is as simple as casting an instance of this
class to string.

Because the canonical string representation of a UUID requires 36 characters,
e.g. 4e917aef-2843-5b3c-8bf5-a858ee6f36bc, which can be quite cumbersome,
this class can also compress and expand UUIDs to and from more compact
representations for storage and other uses. In particular:

 - The Uuid::getBinary() method returns the 128-bit (16 byte) raw binary
   representation of a UUID. This form maintains the same sort order as the
   full form and is the most space-efficient form possible.

 - The Uuid::getShortForm() method returns a customized base 64 encoding of
   the binary form of the UUID. This form is 22 bytes long, maintains the
   same sort order as the full form, and is URL safe.

For convenience, two static methods, Uuid::compress() and Uuid::expand(), are
available in order to simplify the process of converting an existing UUID
string between the full, short, or binary forms.

For the purposes of software applications that use relational databases, the
most useful UUID versions are v7 and v5:

 - UUIDv7 is ideal for generating permanently stored database keys, because
   these UUIDs naturally sort according to their chronological order of
   creation. This is the default version when generating a new UUID.

 - UUIDv5 is ideal for situations where UUIDs need to be generated on demand
   from pre-existing data, but will not be stored permanently. The generation
   algorithm for UUIDv5 always produces the same output given the same input,
   so these UUIDs can be regenerated any number of times without varying.
