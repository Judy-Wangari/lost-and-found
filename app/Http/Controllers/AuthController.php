<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller


{

   // Registration
    public function register(Request $request){
        $validated = $request -> validate ([
        'first_name'=>'required|string',
        'last_name'=>'required|string',
        'email'=>'required|email|unique:users,email',
        'phone_number'=>'required|string',
        'profile_picture'=>'nullable|image|mimes:jpeg,png,jpg',
        'role'=>'required|in:student,admin,security',
        'password'=>'required|confirmed|min:6',
          ]);
      //status based on role
        if($request-> role === 'student'){
            $status = 'active';
        }else{
         $status = 'pending';
        }

     // profile picture
        if($request->hasFile('profile_picture')){
            $filename = $request->file('profile_picture')->store('profiles' ,'public');
              }
        else{
             $filename= null;
        }

     // Saving data after its validated
        $user = new User();
        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->email = $validated['email'];
        $user->phone_number = $validated['phone_number'];
        $user->profile_picture = $filename;
        $user->role = $validated['role'];
        $user->password = Hash::make($validated['password']);
        $user->status = $status;

        try{
            $user -> save();
            
            $token = $user->createToken('auth-token')->plainTextToken;
            if( $status === 'active'){
            //Active accounts(students) go to login page with success message
            $user->profile_picture = $user->profile_picture 
            ? asset('storage/' . $user->profile_picture) 
            : null;
            return response()->json([
                'message'=>'Registration successful. Please Log in.',
                'user' => $user,
                'token' => $token,
            ],201);
            }else{
                //Inactive accounts(admin and security) go to login page with info message
                return response()->json([
                'message'=>'Account pending approval from admin.',
                'user' => $user,
            ],200);
            }
        }
        catch(\Exception $exception){
            return response()->json([
                'error'=>"Registration Failed!",
                'messsage'=>$exception->getMessage()
            ], 500);

        }
    }

    //Login
     public function login(Request $request){
            $validated = $request->validate([
                'email'=>'required|email',
                'password'=>'required|string|min:6'
            ]);

            $user = User::where('email', $validated['email'])->first();

            if(!$user || !Hash::check($validated ['password'], $user->password)){
                throw ValidationException::withMessages ([
                'email'=>'Invalid Credentials.']);
            }
                if( $user->status === 'pending'){
                return response()->json([
                        'message'=>'Your Account is pending approval by the Adminstrator.'],403);
                    }
                
                //Generate token
                $token = $user->createToken('auth-token')->plainTextToken;

                $user->profile_picture = $user->profile_picture 
                ? asset('storage/' . $user->profile_picture) 
                : null;

                return response()->json([
                    'message'=> 'Login Successful!',
                    'user' => $user,
                    'token' => $token,
                ], 200);
     }
    //logout
        public function logout(Request $request){
            $request->user()->currentAccessToken()->delete();
            return response()->json('Logout Successful.');
        }
}
