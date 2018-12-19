conn = new Mongo();
db = conn.getDB("sweetscomplete");
db.purchases.drop();
