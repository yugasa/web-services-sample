<?php
namespace App\Controller\Api;
use App\Controller\AppController;
use Cake\ORM\TableRegistry;
use Cake\Auth\DefaultPasswordHasher;
use Cake\Mailer\Email;
use Cake\I18n\Time;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
     
     // get list of users
    public function index()
    {
	    $this->autoRender = false;
      $user = $this->Users->find('all');
      echo json_encode($user);
    }


    /**
     * View method
     *
     * @param string|null $id User id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    
    //get user by id
    public function view($id = null)
    {    
	    $this->autoRender = false;
      $user = $this->Users->get($id);
      echo json_encode($user);
    }

    
    /**
     * Edit method
     *
     * @param string|null $id User id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
     
     // web service for edit User Info
    public function edit($id = null)
    {
		  $this->autoRender = false;
      $user = $this->Users->get($id);
      if ($this->request->is(['patch', 'post', 'put'])) {
        //edit details of users
  			if(isset($this->request->data['country_id'])){
  				$countries = $this->loadModel('Countries')->find('all')->where(['name'=>$this->request->data['country_id']]);
  				$this->request->data['country_id'] =$countries->first()->id;
  			}
  			if(isset($this->request->data['state_id'])){
  				$states = $this->loadModel('States')->find('all')->where(['name'=>$this->request->data['state_id'],'country_id'=>$this->request->data['country_id']]);
  				$this->request->data['state_id'] =$states->first()->id;
  			}
  			if(isset($this->request->data['city_id'])){
  				$cities = $this->loadModel('Cities')->find('all')->where(['name'=>$this->request->data['city_id'],'state_id'=>$this->request->data['state_id']]);
  				$this->request->data['city_id'] =$cities->first()->id;
  			}
  			if(isset($this->request->data['dob'])){
  				$this->request->data['dob'] = Time::parse($this->request->data['dob']);
  			}
  			if(isset($this->request->data['card_expiration_date'])){
  				$this->request->data['card_expiration_date']= Time::parse($this->request->data['card_expiration_date']);
  			}
        $user = $this->Users->patchEntity($user, $this->request->data);
        if ($this->Users->save($user)) {

            $result['status']='1';
            $result['message']='update successfully';
            $result['Update_user'] = $this->Users->find('all',['contain'=>['Countries','States','Cities']])->where(['Users.id'=>$id]);
             
            
           
        } else {
            $result['status']='0';
            $result['message']='not updated';
        }
      }
      $this->set(compact('user'));
      $this->set('_serialize', ['user']);
      echo json_encode($result);
    }

    /**
     * Delete method
     *
     * @param string|null $id User id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $user = $this->Users->get($id);
        if ($this->Users->delete($user)) {
            $this->Flash->success(__('The user has been deleted.'));
        } else {
            $this->Flash->error(__('The user could not be deleted. Please, try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }
	
    //User Login	
		
    public function login()
    { 
	  
      $this->RequestHandler->config('inputTypeMap.json', ['json_decode', true]);
      $this->autoRender = false;
      if($this->request->is('post'))
      {
        $user = $this->Auth->identify(); 
	      if ($user)
        {  
			
			    $query = $this->loadModel('Users')->find('all',['contain' => ['Cities','States','Countries']])->where(['Users.id'=>$user['id']]);
               $this->Auth->setUser($user);
               $result['status']   = '1';
               $result['message']  = 'You are login successfully'; 
               $result['user_detail']=$query->first();
               //get the dashboard info
               $result['dashboard_info'] =$this->dashboardinfo($user['id']);          
          } else {
               $result['status']   = '0';
               $result['message']  = 'email or password is incorrect';
               $result['user_detail']=null;
               $result['dashboard_info'] =null;             
          }
            
          echo json_encode($result);
      }
   }
   
   // User Registration
   public function addApi()
   {  
      $this->RequestHandler->config('inputTypeMap.json', ['json_decode', true]);
      $this->autoRender = false;
      $user = $this->Users->newEntity();
      if ($this->request->is('post')) 
      {
            $this->request->data['dob'] = Time::parse($this->request->data['dob']);
            $this->request->data['card_expiration_date']=  Time::parse($this->request->data['card_expiration_date']);
            $user = $this->Users->patchEntity($user, $this->request->data);
            $email_id = $this->request->data['email'];
            $query = $this->Users->find()
                    ->where(['Users.email ' => $email_id]);
            if ($query->count() > 0){
                 $result['status']   = '2';
                 $result['message']  = 'Email id Already exist';          
            } else {
                  if ($this->Users->save($user)) {
                      $result['status']   = '1';
                      $result['message']  = 'You are Successfully Registered';
                      $result['User_Detail']=$this->Users->find('all', array('order'=>'id DESC', 'limit'=>1, 'recursive'=>0));
                  } else{
                    // print_r($user->errors());
                     $result['status']   = '0';
                     $result['message']  = 'Not Registered';
                 }
                 
            }
      }
      $this->set(compact('user'));
      $this->set('_serialize', ['user']);
       
      echo json_encode($result);
    }
     
    // web service for forgot password
    public function forgotPassword($email = "")
    { 
		    $this->autoRender = false;
        $this->set('title','MyApp | Users Forgot Password');

        if($this->request->is('post'))
        {    // get email id of admins table
            $email_id = $this->request->data['email']; 
            $query = $this->Users->find('all')
            ->where(['Users.email ' => $email_id]); 
   
               foreach ($query as $row)
               {
                $admin_id = $row->id;
                $user_first_name = $row->first_name; 
                         
               }
             //count number of row.
            $number = $query->count(); 

            if($number == '1')
            { 
                /* generate random for change password */
               // $random=substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVQWXYZ"),0,20);
                $code = rtrim(md5($email_id),"=");

                $forgotPasswordAuthTable = TableRegistry::get('Users');
               
                $query = $forgotPasswordAuthTable->query(); 
                $reset_code = $code;
                // update code on database 
                 $query->update()->set(['reset_code'=>$code])
                        ->where(['email'=>$email_id])->execute();
				
                if($query)
                {                  
                    // gmail Configuration 
                    $status =  $this->sendMail($email_id,$email_id,'MyApp Account | Password Reset Request',"nothing",$code,'Users','verify',$user_first_name,'forgot_password'); 
                    if($status == true)
                    {
						            $result['status']= '1';
						            $result['Msg']= 'Email has been send';
                    }else{
                        $result['status']= '2';
						            $result['Msg']= 'Email not Exist in database';
                    }                   
                }                
              }else{
                $result['status']= '0';
  						  $result['Msg']= 'Email not  send';
            }
            echo json_encode($result);
        }                   
    }
}