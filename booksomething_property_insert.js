conn = new Mongo();
db = conn.getDB("booksomething");
db.property.drop();
