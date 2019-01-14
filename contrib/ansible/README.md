## Deploy it with ansible

In the vagrant-ansible directory there is a Vagrantfile + Ansible playbook (using nginx and Debian Buster)  
Hint: The used Symfony needs php > 7.0 which is currently not in Debian Stretch  


vagrant up  

Create user  
curl localhost:8080/api/user -H "Content-Type: application/json" -d '{ "name": "test", "email": "test@chaos.de" }'  

Show users  
curl localhost:8080/api/user   


