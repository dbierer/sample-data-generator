conn = new Mongo();
db = conn.getDB("booksomething");
db.properties.drop();
