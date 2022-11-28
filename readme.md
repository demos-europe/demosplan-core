# demosplan

This repository contains the demosplan core from [DEMOS](https://demos-deutschland.de).
demosplan is a web-application to support participatory land use planning. Text-and map-based 
information can be displayed and participants can submit comments and annotations. 
The system allows administrators to search, filter and analyze the received contributions. 
Demosplan has a built in role and permission system and provides APIs to connect with add ons.

# License

This project is licensed under the European Public License (EuPL) 1.2. See [license file](LICENSE)
for further details.

# Setup

demosplan is a php application based on the symfony framework that uses mostly vue.js
as a frontend technology.

demosplan core cannot be run as is. This is the base application that needs to be used by
a "project". This project mounts the symfony application and configures the core. To enable
the core features to be activated "permissions" are used as feature toggles.


```text
                            @#                     =++@                
                            +::+@                #+--:=                
                            +:--:*@             =:--::=                
                            *:::::*=@  @@@#@ @=*+:-:::#                
                            @:::::***=#===#*@==*++++::                 
                             +:+:+=*=*#==#==@==***:++:@                
                             =+++*+*+====#*#+@+==**+*@                 
                              #+++:+*++*****++*****++#                 
                              ===++*=#=:+**:*=#**:+=#=                 
                              =*+**+++*=****#+++***:+=+=#              
                              #++++****#=**#=****+*==*=###=#           
                            =::=#=*+*=*+#=#=+***:+**=*==#@@##@         
                       @=+:-:*+::**::**++**++++:+*++#=*##@@@@##        
                     *-+#**+*#++=*++++:::::::+**+*####=###@@@##@       
                 @+:+*=*# @===+*@@##=*******==####@@=#=##@######       
              @+=@@#=*==# @###*+=@@#=====##@@@@  @===##@@#=##@##@      
        @###=#*++=@@#==#@###@@=*===@@#====#  @@=**===##===#@@#==#      
   #===**+****#@#==@#########=+=#==**=##@@===#####==#@=###===*==@      
 #====*+++*=#@#@#==*@@@@@@###++*##@#**==#==##=####@@#@#**++=**#*@      
@     *+++*++#@#####*@##@@=++*===#@@#*==###@@@#=+***====*=++=###@      
      @=#@@##=  @@#=#   =*######@@@@@=###==***++***=#=#*#+*=@##@       
       @ @@@           #*##@@@@@@@@   @=====***=*===#=#=#@@            
                       @####@@                                         
```
