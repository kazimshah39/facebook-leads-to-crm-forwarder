<?php
function mapLeadData($lead_data)
{
  $mapped_data = [];

  // Map the lead ID
  if (isset($lead_data['id'])) {
    $mapped_data['facebook_lead_id'] = $lead_data['id'];
  }

  // Map field data
  if (isset($lead_data['field_data'])) {
    foreach ($lead_data['field_data'] as $field) {
      $key = $field['name'];
      $value = $field['values'][0] ?? '';
      switch ($key) {
        case 'full_name':
          $mapped_data['full_name'] = $value;
          break;
        case 'email':
          $mapped_data['email'] = $value;
          break;
        case 'phone':
          $mapped_data['phone'] = $value;
          break;
        case 'country':
          $mapped_data['country'] = $value;
          break;
        case 'who_is_the_student?':
          $mapped_data['student_type'] = $value;
          break;
        case 'which_course_are_you_interested_in?':
          $mapped_data['course'] = $value;
          break;

        // UTM parameters
        case 'utm_source':
          $mapped_data['utm_source'] = $value;
          break;
        case 'utm_medium':
          $mapped_data['utm_medium'] = $value;
          break;
        case 'utm_campaign':
          $mapped_data['utm_campaign'] = $value;
          break;
        case 'utm_term':
          $mapped_data['utm_term'] = $value;
          break;
        case 'utm_content':
          $mapped_data['utm_content'] = $value;
          break;

        default:
          $mapped_data[$key] = $value;
      }
    }
  }

  $mapped_data['source'] = 'Webhook';

  return $mapped_data;
}
