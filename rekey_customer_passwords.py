# rekey passwords

import bcrypt
in_file = 'sweetscomplete_customers_insert.js'
out_file = 'sweetscomplete_customers_insert_rekeyed.js'
out_f = open(out_file, 'w')
password = 'password'

with open(in_file,'r') as in_f:
    for line in in_f :
        if 'password' in line :
            newHash  = bcrypt.hashpw(password.encode('utf8'),bcrypt.gensalt())
            line = '    "password": "' + str(newHash) + '",' + "\n"
        if 'customerKey' in line :
            print(line)
        out_f.write(line)
            

