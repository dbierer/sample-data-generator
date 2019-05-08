"""
sweetscomplete.authenticate.auth
Description: simple login mechanism which stores user info in a text file, keyed by a token stored in a cookie
"""

import os
import http.cookies

from sweetscomplete.domain.customer import CustomerService
from sweetscomplete.entity.customer import Customer

class SimpleAuth :

    service    = None
    cookieName = 'identifier'
    token      = ''
    # name of the directory where auth info is stored
    baseDir    = ''    
    
    """
    @param sweetscomplete.domain.customer.CustomerService service
    @param string baseDir
    """
    def __init__(self, service, baseDir) :
        self.service  = service
        self.baseDir = baseDir

    """
    @param string email
    @param string password : plain text
    """
    def authByEmail(self, email, password) :
        import bcrypt
        success  = False
        customer = self.service.fetchByEmail(email)
        if customer  and isinstance(customer, Customer) :
            custPass = self.genHash(password)
            success = (custPass == customer.get('password'))
            import pprint
            pprint.pprint(custPass + ':' + customer.get('password'))
            exit
        return customer if success else False
 
    """
    @param string password : plain text
    @return string hashed password
    """
    def genHash(self, password) :
        import bcrypt
        newPass = bcrypt.hashpw(password.encode('utf-8'),bcrypt.gensalt())
        return str(newPass)
 
    def genToken(self) :
        # generates random token
        import random
        import hashlib
        num1 = random.randint(0, 999999)
        num2 = random.randint(0, 999999)
        self.token = hashlib.md5(str(num1) + str(num2)).hexdigest()
        return self.token
                
    def sendTokenCookie(self) :
        # sets the token into a cookie
        import time
        setcookie = http.cookies.SimpleCookie()
        time = datetime.datetime.now() + datetime.timedelta(days=1)
        setcookie[self.cookieName] = self.token
        setcookie[self.cookieName]['path'] = '/'
        setcookie[self.cookieName]['expires'] = time.strftime("%a, %d-%b-%Y %H:%M:%S GMT")
        print(setcookie.output())
        
    def readTokenCookie(self) :
        # sets the token into a cookie
        cookie = Cookie.SimpleCookie()
        cookie.load(os.environ['HTTP_COOKIE'])
        return cookie[self.cookieName].value if self.cookeName in cookie else False

    """
    Builds auth file name from baseDir and token
    Accounts for trailing slash by replacing any instances of "//" in filename with "/"
    @param string token
    @return string authFileName
    """
    def buildAuthFilename(self, token) :
        authFileName = self.baseDir + '/' + token + '.log'
        return authFileName.replace(authFileName, '//', '/')

    """
    @param Customer : customer entity
    @return boolean : True if write operation was successful
    """
    def storeAuthInfo(self, custEntity, token) :
        # build authfilename from token
        authFileName = self.buildAuthFilename(token)
        # store JSON encoded Customer entity in auth file
        f = open(authFileName, 'w')
        result = f.write(custEntity.toJson())
        f.close()
        return result
        
    """
    @return False | Customer : customer entity restored from JSON
    """
    def getIdentity(self) :
        import json
        # retrieve token from cookie
        token = self.readTokenCookie()
        if not token : return False
        # build authfilename from token
        authFileName = self.buildAuthFilename(token)        
        # bail out if file doesn't exist
        if not os.path.exists(authFileName) : return False
        # otherwise, read from file and return Customer instance
        f = open(authFileName, 'r')
        custJson = f.read(4096)
        f.close()
        return Customer(custJson)

    def authenticate(self, username, password) :
        result = self.authByEmail(username, password)
        # if auth is successful, get token, and store customer info in authfile
        if (result) :
            # generate token
            token = self.genToken()
            # store customer info in authfile in JSON format
            self.storeAuthInfo(result, token)                
            # set cookie with token
            self.sendTokenCookie()
            return True
        else :
            return False
