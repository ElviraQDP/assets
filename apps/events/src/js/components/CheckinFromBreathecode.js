import React from 'react';
import {Notify, Notifier} from '@breathecode/react-notifier';
import Empty from './message/Empty';

export default class CheckinFromBreathecode extends React.Component{
    constructor(props){
        super(props);
        this.state={
            idEvent: '',
            email: '',
            first_name: '',
            last_name: '',
            invalidClassEmail: '',
            invalidClassFirst: '',
            invalidClassLast: ''
        }
    }

    static getDerivedStateFromProps(nextProps, prevState) {
        if (nextProps.email !== prevState.email && nextProps.first_name !== prevState.first_name && nextProps.idEvent !== prevState.idEvent) {
            return {
                email: nextProps.email,
                first_name: nextProps.first_name,
                idEvent: nextProps.idEvent
            };
        }
        return null;
    }

    checkinNewUserToEvent(event){
        event.preventDefault();
        const endpointRegisterUserACmp = "https://assets-alesanchezr.c9users.io/apis/event/active_campaign/user?access_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJjbGllbnRJZCI6InJhZmFlc2FhIiwiaWF0IjoxNTMzMzE3NzYyLCJleHAiOjMzMDkwMjY5NzYyfQ.SHiWcnI-lJ-S3bAoUWNtodBTlRH20wkfNuTeQDkKvT0"
        const endpointCheckinEvent = process.env.ACTIVECAMPAING+this.state.idEvent+"/checkin?access_token="+process.env.TOKEN;
        
        console.log(this.state.email.length);
        console.log();
        if(this.state.email.length != 0 && this.state.first_name.length != 0 && this.state.last_name.length != 0){
            console.log('esta lleno');
            //Se guarda en ActiveCampaing con los datos provenientes de Breathecode
            fetch(endpointRegisterUserACmp, {
                headers: {"Content-Type": "application/json"},
                method: 'POST',
                body: JSON.stringify({
                    email: this.state.email,
                    first_name: this.state.first_name,
                    last_name: this.state.last_name
                })
            })
            .then((response)=>{
                console.log('response')
                if (response.ok){
                    console.log('entro en 200');
                    return response.json();
                }else{
                    throw response;
                }
            })
            .then((data)=>{
                console.log('se registro en Active Campaing');
                //Se chekea el usuario en el evento previamente creado en ActiveCampaing
                return fetch(endpointCheckinEvent, {
                                    headers: {"Content-Type": "application/json"},
                                    method: 'PUT',
                                    body: JSON.stringify({email: this.state.email})
                                })
                .then((response)=>{
                    console.log('response')
                    if (response.ok){
                        console.log('entro en 200');
                        return response.json();
                    }else{
                        throw response;
                    }
                })
                .then((data)=>{
                    let noti = Notify.add('info', Success, ()=>{
                        noti.remove();
                    }, 3000);
                    this.props.hiddenFormRegister();
                    this.props.showListUsersInEvent();
                })
                .catch((error)=>{
                    //No chekeao el usuario creado en el evento
                    console.log('no registro el evento', error);
                })
            })
            .catch((error)=>{
                //No registro el usuario en activeCampaing
                console.log('error', error);
            })

        //Se condiciona si el campo esta vacio para mostrar rojo el input
        }else if(this.state.email.length == 0){
            this.setState({
                invalidClassEmail:'is-invalid'
            })
            let noti = Notify.add('info', Empty, ()=>{
                noti.remove();
            }, 3000);
        }else if(this.state.first_name.length == 0){
            this.setState({
                invalidClassFirst:'is-invalid'
            });
            let noti = Notify.add('info', Empty, ()=>{
                noti.remove();
            }, 3000);
        }else if(this.state.last_name.length == 0){
            this.setState({
                invalidClassLast:'is-invalid'
            })
            let noti = Notify.add('info', Empty, ()=>{
                noti.remove();
            }, 3000);
        }else if(this.state.email.length == 0 || this.state.first_name.length == 0 || this.state.last_name.length == 0){
            console.log('no puede estar vacio')
        }
    }

    handleChangeInputEmail(event){
        (event.target.value.length > 0) ?
            this.setState({
                email: event.target.value,
                invalidClassEmail: ''
            })
        : this.setState({
            email: event.target.value,
            invalidClassEmail: 'is-invalid'
        })
    }

    handleChangeInputFirstName(event){
        (event.target.value.length > 0) ?
            this.setState({
                first_name: event.target.value,
                invalidClassFirst: ''
            })
        : this.setState({
            first_name: event.target.value,
            invalidClassFirst: 'is-invalid'
        });
    }

    handleChangeInputLastName(event){
        (event.target.value.length > 0) ?
            this.setState({
                last_name: event.target.value,
                invalidClassLast: ''
            })
        : this.setState({
            last_name: event.target.value,
            invalidClassLast: 'is-invalid'
        })
    }

    render(){
        return(
            <div className="full-width">
            <div className="row justify-content-center full-width">
                <div className="col-md-8 pt-5 pb-5">
                    <form className="form" onSubmit={(event)=>this.checkinNewUserToEvent(event)}>
                    <div className="form-group row">
                        <label className="col-sm-2 col-form-label text-black">Email</label>
                        <div className="col-sm-10">
                        <input 
                            type="text" 
                            className={'form-control '+this.state.invalidClassEmail}
                            placeholder="email"
                            value={this.state.email}
                            onChange={(event)=>this.handleChangeInputEmail(event)}
                            />
                        </div>
                    </div>
                    <div className="form-group row">
                        <label className="col-sm-2 col-form-label text-black">First Name</label>
                        <div className="col-sm-10">
                        <input 
                            type="text" 
                            className={'form-control '+this.state.invalidClassFirst}
                            placeholder="First Name"
                            value={this.state.first_name}
                            onChange={(event)=>this.handleChangeInputFirstName(event)}
                            />
                        </div>
                    </div>
                    <div className="form-group row">
                        <label className="col-sm-2 col-form-label text-black">Last Name</label>
                        <div className="col-sm-10">
                        <input 
                            type="text" 
                            className={'form-control '+this.state.invalidClassLast}
                            placeholder="Last Name"
                            value={this.state.last_name}
                            onChange={(event)=>this.handleChangeInputLastName(event)}
                            />
                        </div>
                    </div>
                        
                    <div className="float-right">
                        <button type="button" className="btn btn-outline-secondary ml-3">Cancel</button>
                        <button type="submit" className="btn btn-outline-success ml-3">Save and Check In</button>
                    </div>
                    </form>
                </div>
            </div>
            </div>
        )
    }
}