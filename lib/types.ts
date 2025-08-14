// Type definitions for the Train Seat App

export interface Train {
  id: number
  train_number: string
  train_name: string
  route: string
  created_at: string
  updated_at: string
}

export interface TrainRun {
  id: number
  train_id: number
  run_date: string
  departure_time: string
  arrival_time: string
  status: string
  created_at: string
  train?: Train
}

export interface CoachType {
  id: number
  type_code: string
  type_name: string
  description: string
  base_fare: number
}

export interface TrainFormation {
  id: number
  train_id: number
  coach_name: string
  coach_type_id: number
  position: number
  total_seats: number
  created_at: string
  coach_type?: CoachType
}

export interface Seat {
  id: number
  formation_id: number
  seat_number: string
  row_number: number
  column_number: number
  seat_type: "window" | "aisle" | "middle" | "regular"
  is_available: boolean
  created_at: string
  formation?: TrainFormation
}

export interface Booking {
  id: number
  train_run_id: number
  seat_id: number
  passenger_name: string
  passenger_email?: string
  passenger_phone?: string
  booking_reference: string
  booking_status: string
  booking_date: string
  fare_amount: number
  created_at: string
  train_run?: TrainRun
  seat?: Seat
}

export interface SearchFilters {
  trainNumber?: string
  trainName?: string
  date?: string
}

export interface SelectedSeat {
  seatId: number
  seatNumber: string
  coachName: string
  coachType: string
  fare: number
}
